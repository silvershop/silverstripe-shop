<?php

namespace SilverShop\Core\Model;


use SilverShop\Core\Account\OrderManipulation;
use SilverShop\Core\Cart\ShoppingCart;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;


/**
 * ShopMember provides customisations to {@link Member} for shop purposes
 *
 * @package shop
 */
class ShopMember extends DataExtension
{
    private static $has_many = [
        'AddressBook' => Address::class,
    ];

    private static $has_one = [
        'DefaultShippingAddress' => Address::class,
        'DefaultBillingAddress' => Address::class,
    ];

    /**
     * Get member by unique field.
     *
     * @return Member|null
     */
    public static function get_by_identifier($idvalue)
    {
        return Member::get()->filter(
            Member::config()->unique_identifier_field,
            $idvalue
        )->first();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Country');
        $fields->removeByName('DefaultShippingAddressID');
        $fields->removeByName('DefaultBillingAddressID');
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'Country',
                _t('SilverShop\Core\Model\Address.db_Country', 'Country'),
                SiteConfig::current_site_config()->getCountriesList()
            )
        );
    }

    public function updateMemberFormFields($fields)
    {
        $fields->removeByName('DefaultShippingAddressID');
        $fields->removeByName('DefaultBillingAddressID');
        if ($gender = $fields->dataFieldByName('Gender')) {
            $gender->setHasEmptyDefault(true);
        }
    }

    /**
     * Link the current order to the current member on login,
     * if there is one, and if configuration is set to do so.
     */
    public function memberLoggedIn()
    {
        if (Member::config()->login_joins_cart && $order = ShoppingCart::singleton()->current()) {
            $order->MemberID = $this->owner->ID;
            $order->write();
        }
    }

    /**
     * Clear the cart, and session variables on member logout
     */
    public function memberLoggedOut()
    {
        if (Member::config()->login_joins_cart) {
            ShoppingCart::singleton()->clear();
            OrderManipulation::clear_session_order_ids();
        }
    }

    /**
     * Get the past orders for this member
     *
     * @return DataList list of orders
     */
    public function getPastOrders()
    {
        return Order::get()
            ->filter('MemberID', $this->owner->ID)
            ->filter('Status:not', Order::config()->hidden_status);
    }
}
