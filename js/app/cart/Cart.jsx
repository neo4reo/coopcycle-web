import React from 'react';
import PropTypes from 'prop-types';
import _ from 'lodash';
import Sticky from 'react-stickynode';

import CartItem from './CartItem.jsx';
import DatePicker from './DatePicker.jsx';
import AddressPicker from "../address/AddressPicker.jsx";

import numeral  from 'numeral';
import 'numeral/locales'

const locale = $('html').attr('lang')

numeral.locale(locale)

class Cart extends React.Component
{
  constructor(props) {
    super(props);

    let { items, total, itemsTotal, adjustments, deliveryDate, streetAddress, isMobileCart, geohash } = this.props;

    this.state = {
      items,
      total,
      itemsTotal,
      adjustments,
      toggled: !isMobileCart,
      date: deliveryDate,
      address: streetAddress,
      geohash: geohash,
      errors: {},
      loading: false
    }

    this.onAddressChange = this.onAddressChange.bind(this)
    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onHeaderClick = this.onHeaderClick.bind(this)
  }

  getCart() {
    const { address, date } = this.state

    return { address, date }
  }

  setCart(cart) {
    this.setState(cart)
  }

  setErrors(errors) {
    this.setState({ errors })
  }

  setLoading(loading) {
    this.setState({ loading })
  }

  onHeaderClick() {
    this.setState({'toggled': !this.state.toggled})
  }

  onAddressChange(geohash, addressString) {
    this.props.onAddressChange(addressString)
  }

  onAddressSelect(geohash, address) {
    this.setState({ address })
    this.onAddressChange(geohash, address)
  }

  renderWarningAlerts(messages) {
    return messages.map((message, key) => (
        <div key={ key } className="alert alert-warning">{ message }</div>
    ))
  }

  renderDangerAlerts(messages) {
    return messages.map((message, key) => (
        <div key={ key } className="alert alert-danger">{ message }</div>
    ))
  }

  renderAdjustments() {
    const { adjustments } = this.state
    if (adjustments.hasOwnProperty('delivery')) {
      return (
        <div>
          { adjustments.delivery.map(adjustment =>
            <div key={ adjustment.id }>
              <span>{ adjustment.label }</span>
              <strong className="pull-right">{ numeral(adjustment.amount / 100).format('0,0.00 $') }</strong>
            </div>
          )}
        </div>
      )
    }
  }

  renderTotal() {
    const { items, total, itemsTotal } = this.state
    const amount = itemsTotal > 0 ? total : itemsTotal

    if (itemsTotal > 0) {
      return (
        <div>
          <hr />
          <div>
            <span>Total produits</span>
            <strong className="pull-right">{ numeral(itemsTotal / 100).format('0,0.00 $') }</strong>
          </div>
          { this.renderAdjustments() }
          <div>
            <span>Total</span>
            <strong className="pull-right">{ numeral(total / 100).format('0,0.00 $') }</strong>
          </div>
        </div>
      )
    }
  }

  render() {

    let { items, total, toggled, errors, date, geohash, address, loading } = this.state,
        cartContent,
        { isMobileCart, availabilities, validateCartURL } = this.props,
        cartTitleKey = isMobileCart ? 'cart.widget.button' : 'Cart'

    if (items.length > 0) {
      let cartItemComponents = items.map((item, key) => {
        return (
          <CartItem
            id={item.id}
            key={key}
            name={item.name}
            total={item.total}
            quantity={item.quantity}
            adjustments={item.adjustments}
            onClickRemove={ () => this.props.onRemoveItem(item) } />
        )
      })

      cartContent = (
        <div className="list-group">{cartItemComponents}</div>
      )
    } else {
      cartContent = ( <div className="alert alert-warning">Votre panier est vide</div> )
    }

    const itemCount = _.reduce(items, function(memo, item) {
      return memo + (item.quantity);
    }, 0)

    const warningAlerts = []
    const dangerAlerts = []

    if (errors) {
      if (errors.total) {
        errors.total.forEach((message, key) => warningAlerts.push(message))
      }
      if (errors.shippingAddress) {
        errors.shippingAddress.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.shippedAt) {
        errors.shippedAt.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.items) {
        errors.items.forEach((message, key) => dangerAlerts.push(message))
      }
    }

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];

    if (items.length === 0 || !address || _.size(errors) > 0 || loading) {
      btnClasses.push('disabled')
    }

    var panelClasses = ['panel', 'panel-default', 'cart-wrapper'];
    if (toggled) {
      panelClasses.push('cart-wrapper--show')
    }

    return (
      <Sticky enabled={!isMobileCart} top={ 30 }>
        <div className={ panelClasses.join(' ') }>
          <div className="panel-heading cart-heading" onClick={ this.onHeaderClick }>
            <span className="cart-heading--items">{ itemCount }</span>
            <span className="cart-heading--total"><i className={ toggled ? "fa fa-chevron-up" : "fa fa-chevron-down"}></i></span>
            { this.props.i18n[cartTitleKey] }
          </div>
          <div className="panel-body">
            { this.renderWarningAlerts(warningAlerts) }
            { this.renderDangerAlerts(dangerAlerts) }
            <div className="cart">
              <AddressPicker
                preferredResults={[]}
                address={address}
                geohash={geohash}
                onPlaceChange={this.onAddressSelect} />
              <hr />
              <DatePicker
                availabilities={availabilities}
                value={date}
                onChange={this.props.onDateChange} />
              <hr />
              { cartContent }
              { this.renderTotal() }
              <hr />
              <a href={validateCartURL} className={btnClasses.join(' ')}>
                { loading && <i className="fa fa-spinner fa-spin"></i> }  <span>Commander</span>
              </a>
            </div>
          </div>
        </div>
      </Sticky>
    );
  }
}

Cart.propTypes = {
  items: PropTypes.arrayOf(PropTypes.object),
  total: PropTypes.number.isRequired,
  adjustments: PropTypes.object.isRequired,
  streetAddress: PropTypes.string,
  deliveryDate: PropTypes.string.isRequired,
  availabilities: PropTypes.arrayOf(PropTypes.string).isRequired,
  isMobileCart: PropTypes.bool.isRequired,
}

module.exports = Cart
