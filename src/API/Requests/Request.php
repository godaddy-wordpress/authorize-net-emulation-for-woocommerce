<?php
/**
 * Authorize.Net Emulation for WooCommerce
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Authorize.Net Emulation for WooCommerce to newer
 * versions in the future. If you wish to customize Authorize.Net Emulation for WooCommerce for your
 * needs please refer to http://www.woocommerce.com/products/ for more information. TODO: docs url
 *
 * @author      SkyVerge
 * @copyright   Copyright (c) 2021, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\Authorize_Net\Emulation\API\Requests;

defined('ABSPATH') or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_10_3 as Framework;

/**
 * Authorize.Net Emulation for WooCommerce API Request Class
 *
 * Generates name/value pair data required by the legacy AIM API
 *
 * @link http://www.authorize.net/support/AIM_guide.pdf
 *
 * @since 1.0.0-dev.1
 */
class Request implements Framework\SV_WC_Payment_Gateway_API_Request
{
    /**
     * Auth/Capture transaction type
     *
     * @since 1.0.0-dev.1
     *
     * @var string
     */
    const AUTH_CAPTURE = 'AUTH_CAPTURE';

    /**
     * Authorize only transaction type
     *
     * @since 1.0.0-dev.1
     *
     * @var string
     */
    const AUTH_ONLY = 'AUTH_ONLY';

    /**
     * Prior auth-only capture transaction type
     *
     * @since 1.0.0-dev.1
     *
     * @var string
     */
    const PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';

    /**
     * Optional order object if this request was associated with an order
     *
     * @since 1.0.0-dev.1
     *
     * @var \WC_Order
     */
    protected $order;

    /**
     * API login ID value.
     *
     * @since 1.0.0-dev.1
     *
     * @var string
     */
    protected $apiLoginId;

    /**
     * API transaction key value.
     *
     * @since 1.0.0-dev.1
     *
     * @var string
     */
    protected $apiTransactionKey;

    /**
     * Request data.
     *
     * @since 1.0.0-dev.1
     *
     * @var array
     */
    protected $requestData;

    /**
     * Constructs request object.
     *
     * @since 1.0.0-dev.1
     *
     * @param string $apiLoginId API login ID
     * @param string $apiTransactionKey API transaction key
     */
    public function __construct(string $apiLoginId, string $apiTransactionKey)
    {
        $this->apiLoginId = $apiLoginId;
        $this->apiTransactionKey = $apiTransactionKey;
    }

    /**
     * Creates a credit card charge request.
     *
     * @since 1.0.0-dev.1
     *
     * @param \WC_Order $order the order object
     */
    public function create_credit_card_charge(\WC_Order $order)
    {
        $this->order = $order;

        $this->create_transaction(self::AUTH_CAPTURE);
    }

    /**
     * Creates a credit card auth request.
     *
     * @since 1.0.0-dev.1
     *
     * @param \WC_Order $order the order object
     */
    public function create_credit_card_auth(\WC_Order $order)
    {
        $this->order = $order;

        $this->create_transaction(self::AUTH_ONLY);
    }

    /**
     * Captures funds for a previous credit card authorization.
     *
     * @since 1.0.0-dev.1
     *
     * @param \WC_Order $order the order object
     */
    public function create_credit_card_capture(\WC_Order $order)
    {
        $this->order = $order;

        $this->requestData = [
            'x_type'     => self::PRIOR_AUTH_CAPTURE,
            'x_amount'   => $order->capture->amount,
            'x_trans_id' => $order->capture->trans_id,
        ];
    }

    /** Request Helper Methods ******************************************************/

    /**
     * Creates the transaction XML, this handles all transaction types and both credit card/eCheck transactions
     *
     * @since 1.0.0-dev.1
     *
     * @param string $type transaction type
     */
    private function create_transaction(string $type)
    {
        $this->requestData = [
            'x_type'          => $type,
            'x_amount'        => $this->get_order()->payment_total,
            'x_currency_code' => $this->get_order()->get_currency(),
            'x_card_num'      => $this->order->payment->account_number,
            'x_exp_date'      => sprintf('%s-%s', $this->get_order()->payment->exp_month, $this->get_order()->payment->exp_year),
            'x_card_code'     => $this->order->payment->csc,
            'x_invoice_num'   => $this->get_order()->get_order_number(),
            'x_description'   => Framework\SV_WC_Helper::str_truncate($this->get_order()->description, 255),
            'x_line_item'     => $this->get_line_items(),
            'x_tax'           => $this->get_order()->get_total_tax(),
            'x_freight'       => $this->get_order()->get_shipping_total(),
            'x_email'         => is_email($this->get_order()->get_billing_email()) ? $this->get_order()->get_billing_email() : '',
            'x_cust_id'       => $this->order->get_user_id(),
            'x_customer_ip'   => $this->get_order()->get_customer_ip_address(),
        ];

        $this->setAddresses();
    }

    /**
     * Sets the billing and shipping address information for the request.
     *
     * @since 1.0.0-dev.1
     */
    private function setAddresses()
    {
        $billingAddress = trim($this->get_order()->get_billing_address_1().' '.$this->get_order()->get_billing_address_2());
        $shippingAddress = trim($this->get_order()->get_shipping_address_1().' '.$this->get_order()->get_shipping_address_2());
        $billingCountry = Framework\Country_Helper::convert_alpha_country_code($this->get_order()->get_billing_country());
        $shippingCountry = Framework\Country_Helper::convert_alpha_country_code($this->get_order()->get_shipping_country());

        // address fields
        $fields = [
            'billing'  => [
                'first_name' => ['value' => $this->get_order()->get_billing_first_name(), 'limit' => 50],
                'last_name'  => ['value' => $this->get_order()->get_billing_last_name(), 'limit' => 50],
                'company'    => ['value' => $this->get_order()->get_billing_company(), 'limit' => 50],
                'address'    => ['value' => $billingAddress, 'limit' => 60],
                'city'       => ['value' => $this->get_order()->get_billing_city(), 'limit' => 40],
                'state'      => ['value' => $this->get_order()->get_billing_state(), 'limit' => 40],
                'zip'        => ['value' => $this->get_order()->get_billing_postcode(), 'limit' => 20],
                'country'    => ['value' => $billingCountry, 'limit' => 60],
                'phone'      => ['value' => $this->get_order()->get_billing_phone(), 'limit' => 25],
            ],
            'shipping' => [
                'first_name' => ['value' => $this->get_order()->get_shipping_first_name(), 'limit' => 50],
                'last_name'  => ['value' => $this->get_order()->get_shipping_last_name(), 'limit' => 50],
                'company'    => ['value' => $this->get_order()->get_shipping_company(), 'limit' => 50],
                'address'    => ['value' => $shippingAddress, 'limit' => 60],
                'city'       => ['value' => $this->get_order()->get_shipping_city(), 'limit' => 40],
                'state'      => ['value' => $this->get_order()->get_shipping_state(), 'limit' => 40],
                'zip'        => ['value' => $this->get_order()->get_shipping_postcode(), 'limit' => 20],
                'country'    => ['value' => $shippingCountry, 'limit' => 60],
            ],
        ];

        foreach (['billing', 'shipping'] as $type) {
            foreach ($fields[$type] as $fieldName => $field) {
                if ('phone' === $fieldName) {
                    $value = preg_replace('/\D/', '', $field['value']);
                } else {

                    // authorize.net claims to support unicode, but not all code points yet. Unrecognized code points will display in their control panel with question marks
                    $value = Framework\SV_WC_Helper::str_to_sane_utf8($field['value']);
                }

                if ($value) {
                    $key = 'billing' === $type ? 'x_'.$fieldName : 'x_ship_to_'.$fieldName;

                    $this->requestData[$key] = Framework\SV_WC_Helper::str_truncate($value, $field['limit']);
                }
            }
        }
    }

    /**
     * Adds line items to the request.
     *
     * @since 1.0.0-dev.1
     *
     * @return array
     */
    protected function get_line_items() : array
    {
        $lineItems = [];

        // order line items
        foreach (Framework\SV_WC_Helper::get_order_line_items($this->get_order()) as $item) {
            if ($item->item_total >= 0) {

                // in order: item ID, nam, description, quantity, unit price, taxable or not
                $lineItems[] = implode('<|>', [
                    $item->id,
                    Framework\SV_WC_Helper::str_to_sane_utf8(Framework\SV_WC_Helper::str_truncate($item->name, 31)),
                    Framework\SV_WC_Helper::str_to_sane_utf8(Framework\SV_WC_Helper::str_truncate($item->description, 255)),
                    $item->quantity,
                    Framework\SV_WC_Helper::number_format($item->item_total),
                    is_callable([$item->product, 'is_taxable']) ? $item->product->is_taxable() : false
                ]);
            }
        }

        // authorize.net only allows 30 line items per order
        if (count($lineItems) > 30) {
            $lineItems = array_slice($lineItems, 0, 30);
        }

        return $lineItems;
    }

    /**
     * Gets the request data.
     *
     * @since 1.0.0-dev.1
     *
     * @return array
     */
    public function get_data() : array
    {

        // required for every transaction
        $transaction_data = [
            'x_login'           => $this->apiLoginId,
            'x_tran_key'        => $this->apiTransactionKey,
            'x_relay_response'  => 'FALSE', // does not accept a boolean
            'x_response_format' => '2',
            'x_delim_data'      => 'TRUE', // does not accept a boolean
            'x_delim_char'      => '|',
            'x_encap_char'      => ':',
            'x_solution_id'     => 'A1000065',
            'x_version'         => '3.1',
            'x_method'          => 'CC',
        ];

        // add request data
        $this->requestData = array_merge($transaction_data, $this->requestData);

        /**
         * Filters the API the request data before it's sent to Authorize.Net.
         *
         * @since 1.0.0-dev.1
         *
         * @param array     $data request data to be filtered
         * @param \WC_Order $order order instance
         * @param Request   $this , API request class instance
         */
        $this->requestData = apply_filters('wc_authorize_net_aim_api_request_data', $this->requestData, $this->order, $this);

        // remove any empty fields
        foreach ($this->requestData as $key => $value) {
            if ('' === $value || null === $value) {
                unset($this->requestData[$key]);
            }
        }

        return $this->requestData;
    }

    /** API Helper Methods ******************************************************/

    /**
     * Gets the string representation of the request.
     *
     * @see Framework\SV_WC_Payment_Gateway_API_Request::to_string()
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function to_string() : string
    {
        return http_build_query($this->get_data(), '', '&');
    }

    /**
     * Gets the string representation of this request with any and all
     * sensitive elements masked or removed.
     *
     * @see Framework\SV_WC_Payment_Gateway_API_Request::to_string_safe()
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function to_string_safe() : string
    {
        $this->requestData = $this->get_data();

        // login ID/transaction key
        $this->requestData['x_login'] = str_repeat('*', strlen($this->requestData['x_login']));
        $this->requestData['x_tran_key'] = str_repeat('*', strlen($this->requestData['x_tran_key']));

        // credit card number
        if (isset($this->requestData['x_card_num'])) {
            $this->requestData['x_card_num'] = substr($this->requestData['x_card_num'], 0, 1).str_repeat('*', strlen($this->requestData['x_card_num']) - 5).substr($this->requestData['x_card_num'], -4);
        }

        // credit card CSC
        if (isset($this->requestData['x_card_code'])) {
            $this->requestData['x_card_code'] = str_repeat('*', strlen($this->requestData['x_card_code']));
        }

        return rawurldecode(http_build_query($this->requestData, '', '&'));
    }

    /**
     * Gets the order associated with this request, if there was one.
     *
     * @since 1.0.0-dev.1
     *
     * @return \WC_Order
     */
    public function get_order() : \WC_Order
    {
        return $this->order;
    }

    /**
     * Gets the request method.
     *
     * This is always POST.
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function get_method() : string
    {
        return 'POST';
    }

    /**
     * Gets the request path.
     *
     * @since 1.0.0-dev.1
     *
     * @return string
     */
    public function get_path() : string
    {
        return '';
    }

    /**
     * Gets the request parameters.
     *
     * @since 1.0.0-dev.1
     *
     * @return array
     */
    public function get_params() : array
    {
        return [];
    }
}
