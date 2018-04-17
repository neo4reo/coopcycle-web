<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\DeliveryAddressType;
use AppBundle\Form\StripePaymentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/order")
 */
class OrderController extends Controller
{
    /**
     * @Route("/", name="order")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $order = $this->get('sylius.context.cart')->getCart();

        $orderManager = $this->get('coopcycle.order_manager');

        // TODO Check if cart is empty

        $deliveryAddress = $order->getShippingAddress();

        $form = $this->createForm(DeliveryAddressType::class, $deliveryAddress);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $deliveryAddress = $form->getData();
            $this->getDoctrine()->getManagerForClass(Address::class)->persist($deliveryAddress);
            $this->getDoctrine()->getManagerForClass(Address::class)->flush();

            // TODO Set customer via listeners
            $order->setCustomer($this->getUser());

            // Create order, to generate a number
            $orderManager->create($order);

            $this->get('sylius.manager.order')->flush();

            return $this->redirectToRoute('order_payment', array('number' => $order->getNumber()));
        }

        return array(
            'order' => $order,
            'form' => $form->createView(),
            'restaurant' => $order->getRestaurant(),
            'deliveryAddress' => $deliveryAddress,
        );
    }

    /**
     * @Route("/payment/{number}", name="order_payment")
     * @Template()
     */
    public function paymentAction($number, Request $request)
    {


        $order = $this->get('sylius.repository.order')->findOneBy([
            'number' => $number
        ]);

        $stripePayment = $order->getLastPayment(PaymentInterface::STATE_CART);

        $stateMachineFactory = $this->get('sm.factory');
        $stateMachine = $stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

        $form = $this->createForm(StripePaymentType::class);

        $parameters =  [
            'order' => $order,
            'deliveryAddress' => $order->getShippingAddress(),
            'restaurant' => $order->getRestaurant(),
            'form' => $form->createView(),
        ];

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $stripePayment->setStripeToken($form->get('stripeToken')->getData());

            $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);

            $this->get('sylius.manager.order')->flush();

            if (PaymentInterface::STATE_FAILED === $stripePayment->getState()) {
                return array_merge($parameters, [
                    'error' => $stripePayment->getLastError()
                ]);
            }

            $sessionKeyName = $this->getParameter('sylius_cart_restaurant_session_key_name');
            $request->getSession()->remove($sessionKeyName);

            return $this->redirectToRoute('profile_order', array('id' => $order->getId()));
        }

        return $parameters;
    }
}
