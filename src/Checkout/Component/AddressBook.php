<?php

namespace SilverShop\Core\Checkout\Component;


use SilverShop\Core\Model\Order;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\i18n\i18nEntityProvider;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;


/**
 * Adds the ability to use the member's address book for choosing addresses
 *
 */
abstract class AddressBook extends Address implements i18nEntityProvider
{
    /**
     * @config the composite field tag to use
     * @var string
     */
    private static $composite_field_tag = 'div';

    protected $addtoaddressbook = true;

    public function getFormFields(Order $order)
    {
        $fields = parent::getFormFields($order);

        if ($existingaddressfields = $this->getExistingAddressFields()) {
            //TODO: Get rid of JavaScript here
            Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.min.js');
            Requirements::javascript(SHOP_DIR . '/javascript/CheckoutPage.js');
            // add the fields for a new address after the dropdown field
            $existingaddressfields->merge($fields);
            // group under a composite field (invisible by default) so we
            // easily know which fields to show/hide
            $label = _t(
                "SilverShop\Core\Model\Address.{$this->addresstype}Address",
                "{$this->addresstype} Address"
            );

            return FieldList::create(
                CompositeField::create($existingaddressfields)
                    ->addExtraClass('hasExistingValues')
                    ->setLegend($label)
                    ->setTag(Config::inst()->get(self::class, 'composite_field_tag'))
            );
        }

        return $fields;
    }

    /**
     * Allow choosing from an existing address
     *
     * @return FieldList|null fields for
     */
    public function getExistingAddressFields()
    {
        $member = Security::getCurrentUser();
        if ($member && $member->AddressBook()->exists()) {
            $addressoptions = $member->AddressBook()->sort('Created', 'DESC')->map('ID', 'toString')->toArray();
            $addressoptions['newaddress'] = _t('SilverShop\Core\Model\Address.CreateNewAddress', 'Create new address');
            $fieldtype = count($addressoptions) > 3 ? DropdownField::class : OptionsetField::class;

            $label = _t("SilverShop\Core\Model\Address.Existing{$this->addresstype}Address", "Existing {$this->addresstype} Address");

            return FieldList::create(
                $fieldtype::create(
                    $this->addresstype . 'AddressID',
                    $label,
                    $addressoptions,
                    $member->{'Default' . $this->addresstype . 'AddressID'}
                )->addExtraClass('existingValues')
            );
        }

        return null;
    }

    /**
     * We don't know at the front end which fields are required so we defer to validateData
     *
     * @param Order $order
     *
     * @return array
     */
    public function getRequiredFields(Order $order)
    {
        return [];
    }

    /**
     * @param Order $order
     * @param array $data
     *
     * @throws ValidationException
     */
    public function validateData(Order $order, array $data)
    {
        $result = ValidationResult::create();
        $existingID =
            !empty($data[$this->addresstype . 'AddressID']) ? (int)$data[$this->addresstype . 'AddressID'] : 0;

        if ($existingID) {
            $member = Security::getCurrentUser();
            // If existing address selected, check that it exists in $member->AddressBook
            if (!$member || !$member->AddressBook()->byID($existingID)) {
                $result->error('Invalid address supplied', $this->addresstype . 'AddressID');
                throw new ValidationException($result);
            }
        } else {
            // Otherwise, require the normal address fields
            $required = parent::getRequiredFields($order);
            $addressLabels = singleton('Address')->fieldLabels(false);

            foreach ($required as $fieldName) {
                if (empty($data[$fieldName])) {
                    // attempt to get the translated field name
                    $fieldLabel = isset($addressLabels[$fieldName]) ? $addressLabels[$fieldName] : $fieldName;
                    $errorMessage = _t(
                        'SilverStripe\\Forms\\Form.FIELDISREQUIRED',
                        '{name} is required',
                        array('name' => $fieldLabel)
                    );

                    $result->error($errorMessage, $fieldName);
                    throw new ValidationException($result);
                }
            }
        }
    }

    /**
     * Create a new address if the existing address has changed, or is not yet
     * created.
     *
     * @param Order $order order to get addresses from
     * @param array $data data to set
     * @throws ValidationException
     */
    public function setData(Order $order, array $data)
    {
        $existingID =
            !empty($data[$this->addresstype . 'AddressID']) ? (int)$data[$this->addresstype . 'AddressID'] : 0;
        if ($existingID > 0) {
            $order->{$this->addresstype . 'AddressID'} = $existingID;
            $order->write();
            $order->extend('onSet' . $this->addresstype . 'Address', $address);
        } else {
            parent::setData($order, $data);
        }
    }

    /**
     * Provide translatable entities for this class
     *
     * @return array
     */
    public function provideI18nEntities()
    {
        if ($this->addresstype) {
            return [

                "SilverShop\Core\Model\Address.{$this->addresstype}Address" => [
                    "{$this->addresstype} Address",
                    "Label for the {$this->addresstype} address",
                ],
                "SilverShop\Core\Model\Address.Existing{$this->addresstype}Address" => [
                    "Existing {$this->addresstype} Address",
                    "Label to select an existing {$this->addresstype} Address",
                ],
            ];
        }

        return array();
    }
}
