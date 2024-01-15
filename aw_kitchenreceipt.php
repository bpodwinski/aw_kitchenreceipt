<?php

/**
 * 2024 Artisan Webmaster
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Artisan Webmaster <contact@artisanwebmaster.com>
 * @copyright 2024 Artisan Webmaster
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class aw_kitchenreceipt extends Module
{
    const PREFIX = 'AW_KR_';

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function __construct()
    {
        $this->name = 'aw_kitchenreceipt';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'Artisan Webmaster';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Kitchen Receipt Auto Print', [], 'Modules.Awkitchenreceipt.Admin');
        $this->description = $this->trans('Generates a PDF and automatically prints kitchen receipts for restaurants and cafes upon a new order', [], 'Modules.Awkitchenreceipt.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Admin.Notifications.Warning');
    }

    /**
     * install pre-config
     *
     * @return bool
     */
    public function install()
    {

        // Add custom font for TCPDF
        $pathTTFFiles = array(
            _PS_MODULE_DIR_ . $this->name . '/vendor/fonts/Ticketing.ttf'
        );

        foreach ($pathTTFFiles as $ttfFile) {

            if (!file_exists($ttfFile)) {
                return false;
            }

            $fontname = TCPDF_FONTS::addTTFfont($ttfFile, '', '', 96, _PS_MODULE_DIR_ . $this->name . '/vendor/fonts');
            if ($fontname === false) {
                return false;
            }
        }

        // Configuration
        Configuration::updateValue(self::PREFIX . 'PDF_FOR_PRINTING', false);
        Configuration::updateValue(self::PREFIX . 'PDF_GENERATION', true);
        Configuration::updateValue(self::PREFIX . 'PDF_ORIENTATION', 'P');
        Configuration::updateValue(self::PREFIX . 'PDF_UNIT_MEASURE', 'mm');
        Configuration::updateValue(self::PREFIX . 'PDF_WIDTH', '80');

        // Hooks
        if (
            parent::install() &&
            $this->registerHook('actionValidateOrder')
        ) {
            return true;
        }

        $this->_errors[] = $this->trans('There was an error during the installation.', [], 'Modules.Blockreassurance.Admin');

        return false;
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    public function uninstall()
    {
        // Configuration
        Configuration::deleteByName(self::PREFIX . 'PDF_FOR_PRINTING');
        Configuration::deleteByName(self::PREFIX . 'PDF_GENERATION');
        Configuration::deleteByName(self::PREFIX . 'PDF_ORIENTATION');
        Configuration::deleteByName(self::PREFIX . 'PDF_UNIT_MEASURE');
        Configuration::deleteByName(self::PREFIX . 'PDF_WIDTH');

        // Hooks
        if (
            parent::uninstall() ||
            $this->unregisterHook('actionValidateOrder')
        ) {
            return true;
        }

        $this->_errors[] = $this->trans('There was an error during the uninstallation.', [], 'Modules.Blockreassurance.Admin');

        return false;
    }

    /**
     * Generates a PDF for a specific order.
     *
     * This function takes an order ID as input and generates a PDF file. The PDF contains
     * the order reference, the order date, and a list of all products in the order with their
     * quantities and total price (including tax). Depending on the $output parameter, the PDF can be displayed
     * directly in the browser, downloaded, or saved on the server. The default path for saving
     * the PDF is the 'pdf' directory of the module.
     *
     * @param int $orderId The ID of the order for which the PDF should be generated.
     * @param string $output The PDF output mode ('I' for display, 'D' for download, 'F' for save on server, 'FI' for both save and display, 'FD' for both save and download).
     * @param string $filePath Custom path to save the PDF file (optional).
     *
     * @throws Exception If the order is not found or if an error occurs during PDF generation.
     *
     * @return void
     */
    public function generatePdf($orderId, $output = 'I', $filePath = '')
    {
        try {
            require_once(_PS_MODULE_DIR_ . $this->name . '/aw_pdf.php');

            // Check if order ID is valid
            if (!Validate::isUnsignedId($orderId)) {
                throw new Exception('Invalid order ID');
            }

            // Load the order
            $order = new Order((int)$orderId);

            // Check if the order exists
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Order not found');
            }

            // Get customer and products order
            $customerOrder = $this->getCustomerOrder($orderId);
            $productsOrder = $order->getProducts();

            $iterationCount = 0;
            foreach ($productsOrder as $product) {
                $iterationCount++;
            }

            $pdfOrientation = Configuration::get(self::PREFIX . 'PDF_ORIENTATION');
            $pdfUnitMeasure = Configuration::get(self::PREFIX . 'PDF_UNIT_MEASURE');
            $pdfWidth = Configuration::get(self::PREFIX . 'PDF_WIDTH');
            $pdfHeight = (int)$iterationCount * 15 + 80;
            $pdfMarginLeft = 2;
            $pdfMarginTop = 22;
            $pdfMarginRight = 2;

            $pdf = new AWPDF($pdfOrientation, $pdfUnitMeasure, array($pdfWidth, $pdfHeight), true, 'UTF-8', false);
            $pdf->SetAutoPageBreak(true, 0); // Set the auto page break to create a new page when the content reaches the bottom
            $pdf->SetMargins($pdfMarginLeft, $pdfMarginTop, $pdfMarginRight);
            $pdf->SetHeaderMargin(1);
            $pdf->SetFooterMargin(5);
            $pdf->AddPage($pdfOrientation, array($pdfWidth, $pdfHeight), true, false);
            $pdf->SetFont('ticketing', '', 9);
            $pdf->setPrintHeader(false);

            if (class_exists('PrestatillDriveCreneau')) {

                $pdf->Cell(0, 0, $this->trans('Order details', [], 'Modules.Awkitchenreceipt.Admin'), 1, 2, 'C');

                $pdf->SetX($pdfMarginLeft - 1);
                $pdf->Cell(0, 0, $this->trans('Order #:', [], 'Modules.Awkitchenreceipt.Admin'), 0, 0, 'L');
                $pdf->Cell(0, 0, $order->reference, 0, 1, 'R');

                $pdf->SetX($pdfMarginLeft - 1);
                $pdf->Cell(0, 0, $this->trans('Created at:', [], 'Modules.Awkitchenreceipt.Admin'), 0, 0, 'L');
                $pdf->Cell(0, 0, $order->date_add, 0, 1, 'R');

                $pdf->SetX($pdfMarginLeft - 1);
                $pdf->Cell(0, 0, $this->trans('Customer:', [], 'Modules.Awkitchenreceipt.Admin'), 0, 0, 'L');
                $pdf->Cell(0, 0, $customerOrder->firstname . ' ' . $customerOrder->lastname, 0, 1, 'R');

                $html = '<div>';

                foreach ($productsOrder as $product) {
                    $parts = explode(' (', $product['product_name'], 2);
                    $title = $parts[0];
                    $rest = '(' . $parts[1];
                    $cleanTxt = str_replace(array('(', ')'), '', $rest);
                    $splits = preg_split('/ - |, /', $cleanTxt);

                    $split_attributs = '';
                    foreach ($splits as $split) {
                        $split_attributs .= '<li>' . $split . '</li>';
                    }

                    $html .= '<h2 style="font-size: 9pt; line-height: 0.9;">' . $product['product_quantity'] . ' x ' . $title . '</h2>';
                    $html .= '<ul style="list-style-type: none; line-height: 1.1;">';
                    $html .= $split_attributs;
                    $html .= '</ul>';
                }

                $html .= '</div>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }

            // Save the PDF
            $filePath = _PS_MODULE_DIR_ . $this->name . '/pdf/';
            $pdfFileName = 'order_' . date('YmdHis', strtotime($orderDateTime)) . '.pdf';
            $pdfFilePath = $filePath . $pdfFileName;

            if (in_array($output, ['I', 'D'])) {
                $pdf->Output($pdfFileName, $output);
            }
            if (in_array($output, ['F', 'FI', 'FD'])) {
                $pdf->Output($pdfFilePath, $output);
            } else {
                $this->displayError($this->trans('Error generating PDF', [], 'Modules.Awkitchenreceipt.Admin'));
            }
        } catch (Exception $e) {
            $this->context->controller->errors[] = $this->trans('Error: ', [], 'Admin.Notifications.Error') . $e->getMessage();
        }
    }

    /**
     * Handles the module configuration form submission and generates a PDF for the specified order ID.
     * Processes settings updates from the configuration form and handles PDF generation requests.
     * Displays error messages if the order ID is invalid or if any issues occur during the PDF generation.
     * Displays confirmation messages upon successful settings update or PDF generation.
     *
     * @return string HTML content including confirmation or error messages, followed by the configuration form.
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name . 'FormSettings')) {

            try {
                $settings = array(
                    self::PREFIX . 'PDF_GENERATION' => Tools::getValue('PDF_GENERATION'),
                    self::PREFIX . 'PDF_ORIENTATION' => Tools::getValue('PDF_ORIENTATION'),
                    self::PREFIX . 'PDF_UNIT_MEASURE' => Tools::getValue('PDF_UNIT_MEASURE'),
                    self::PREFIX . 'PDF_WIDTH' => Tools::getValue('PDF_WIDTH')
                );

                foreach ($settings as $configKey => $value) {
                    Configuration::updateValue($configKey, $value);
                }

                $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Awkitchenreceipt.Admin'));
            } catch (Exception $e) {
                $output .= $this->displayError($this->trans('Error when updating settings', [], 'Modules.Awkitchenreceipt.Admin'));
            }
        }

        if (Tools::isSubmit('submit' . $this->name . 'FormOrderID')) {
            $orderId = Tools::getValue('ORDER_ID');

            if (!Validate::isUnsignedId($orderId)) {
                $output .= $this->displayError($this->trans('Invalid value or empty', [], 'Modules.Awkitchenreceipt.Admin'));
            } else {
                $this->generatePdf($orderId, 'D');
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Generates the module's configuration form for the back-office. The form includes a field to enter the order ID
     * for which to generate a PDF.
     *
     * The form also includes buttons to save the data and return to the module list.
     * It allows setting various PDF generation parameters such as orientation, unit of measurement, and width.
     * Additionally, it provides an option to enable or disable automatic PDF generation upon order creation.
     *
     * @return string HTML of the form to be displayed.
     */
    public function displayForm()
    {

        // Get default Language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $formOrderID = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('By order ID', [], 'Modules.Awkitchenreceipt.Admin'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Order ID', [], 'Modules.Awkitchenreceipt.Admin'),
                        'name' => 'ORDER_ID',
                        'desc' => $this->trans('Enter the Order ID you want to generate a PDF for.', [], 'Modules.Awkitchenreceipt.Admin')
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Send for printing', [], 'Modules.Awkitchenreceipt.Admin'),
                        'name' => 'PDF_FOR_PRINTING',
                        'desc' => $this->trans('Also generates a PDF file on the server for printing.', [], 'Modules.Awkitchenreceipt.Admin'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    )
                ),
                'submit' => array(
                    'title' => $this->trans('Generate PDF file', [], 'Modules.Awkitchenreceipt.Admin'),
                    'name' => 'submit' . $this->name . 'FormOrderID',
                    'class' => 'btn btn-default btn-primary pull-right'
                ),
            ),
        );

        $formSettings = array(
            'form' => array(
                'legend' => array('title' => $this->trans('Settings', [], 'Modules.Awkitchenreceipt.Admin')),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Generate a PDF when an order is created'),
                        'name' => 'PDF_GENERATION',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Orientation', [], 'Modules.Awkitchenreceipt.Admin'),
                        'name' => 'PDF_ORIENTATION',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'P',
                                    'name' => $this->trans('Portrait', [], 'Modules.Awkitchenreceipt.Admin')
                                ),
                                array(
                                    'id_option' => 'L',
                                    'name' => $this->trans('Landscape', [], 'Modules.Awkitchenreceipt.Admin')
                                )
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                        'default_value' => 'P',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Unit of measurement', [], 'Modules.Awkitchenreceipt.Admin'),
                        'name' => 'PDF_UNIT_MEASURE',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'pt',
                                    'name' => $this->trans('Point', [], 'Modules.Awkitchenreceipt.Admin')
                                ),
                                array(
                                    'id_option' => 'mm',
                                    'name' => $this->trans('Millimeter', [], 'Modules.Awkitchenreceipt.Admin')
                                ),
                                array(
                                    'id_option' => 'cm',
                                    'name' => $this->trans('Centimeter', [], 'Modules.Awkitchenreceipt.Admin')
                                ),
                                array(
                                    'id_option' => 'in',
                                    'name' => $this->trans('Inch', [], 'Modules.Awkitchenreceipt.Admin')
                                )
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                        'default_value' => 'mm',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Width', [], 'Modules.Awkitchenreceipt.Admin'),
                        'name' => 'PDF_WIDTH',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', [], 'Modules.Awkitchenreceipt.Admin'),
                    'name' => 'submit' . $this->name . 'FormSettings',
                    'class' => 'btn btn-default btn-primary pull-right'
                )
            ),
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->trans('Save', [], 'Modules.Awkitchenreceipt.Admin'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->trans('Back to list', [], 'Modules.Awkitchenreceipt.Admin')
            )
        );

        // Load current value
        $helper->fields_value['ORDER_ID'] = '';
        $helper->fields_value['PDF_FOR_PRINTING'] = Configuration::get(self::PREFIX . 'PDF_FOR_PRINTING');
        $helper->fields_value['PDF_GENERATION'] = Configuration::get(self::PREFIX . 'PDF_GENERATION');
        $helper->fields_value['PDF_ORIENTATION'] = Configuration::get(self::PREFIX . 'PDF_ORIENTATION');
        $helper->fields_value['PDF_UNIT_MEASURE'] = Configuration::get(self::PREFIX . 'PDF_UNIT_MEASURE');
        $helper->fields_value['PDF_WIDTH'] = Configuration::get(self::PREFIX . 'PDF_WIDTH');

        return $helper->generateForm(array($formOrderID, $formSettings));
    }

    public function getCustomerOrder($orderId)
    {
        try {

            // Check if order ID is valid
            if (!Validate::isUnsignedId($orderId)) {
                throw new Exception('Invalid order ID');
            }

            // Load the order
            $order = new Order((int)$orderId);

            // Check if the order exists
            if (!Validate::isLoadedObject($order)) {
                throw new Exception('Order not found');
            }

            $customerId = $order->id_customer;
            $customer = new Customer((int)$customerId);

            return $customer;
        } catch (Exception $e) {
            $this->context->controller->errors[] = $this->trans('Error: ', [], 'Admin.Notifications.Error') . $e->getMessage();
        }
    }

    /**
     * Listens to the 'actionValidateOrder' event and generates a PDF for the order that has just been placed.
     *
     * This function is automatically called each time an order is placed, thanks to the 'actionValidateOrder' hook.
     * It retrieves the order ID from the event parameters and calls the 'generatePdf' method to generate the PDF.
     * If PDF generation is enabled in the module settings, it proceeds to generate a PDF file and save it in the specified directory.
     *
     * @param array $params Parameters passed by the hook, containing information about the order.
     *
     * @return void
     */
    public function hookactionValidateOrder($params)
    {
        try {
            $pdfGenerationEnabled = Configuration::get(self::PREFIX . 'PDF_GENERATION');

            if ($pdfGenerationEnabled) {
                // Get the ID and date of the order from the parameters
                $orderId = $params['order']->id;
                $orderDateTime = $params['order']->date_add;

                // Call the function to generate the PDF
                $pdfFileName = 'order_' . date('YmdHis', strtotime($orderDateTime)) . '.pdf';
                $pdfFilePath = _PS_MODULE_DIR_ . $this->name . '/pdf/' . $pdfFileName;

                $this->generatePdf($orderId, 'F', $pdfFilePath);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error generating PDF for order ' . $orderId . ': ' . $e->getMessage(), 4, null, 'Order', $orderId, true);
        }
    }
}
