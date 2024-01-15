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

require_once(_PS_MODULE_DIR_ . "prestatilldrive/prestatilldrive.php");
require_once(_PS_MODULE_DIR_ . "prestatilldrive/classes/PrestatillDriveCreneau.php");

// Override Header of the TCPDF
class AWPDF extends TCPDF
{
    protected $orderDateTime;
    protected $orderReference;
    protected $orderCustomer;
    protected $orderId;

    public function setOrderDateTime($orderDateTime)
    {
        $this->orderDateTime = $orderDateTime;
    }

    public function setOrderCustomer($orderCustomer)
    {
        $this->orderCustomer = $orderCustomer;
    }

    public function setOrderReference($orderReference)
    {
        $this->orderReference = $orderReference;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
    }

    public function Header()
    {
        $this->SetFont("ticketing", "B", 9);

        $creneau = PrestatillDriveCreneau::getCreneauByIdOrder($this->orderId);

        if (class_exists("PrestatillDriveCreneau") && Validate::isLoadedObject($creneau)) {

            if ((int)$creneau->id_store > 0 && $creneau->id_order > 0) {
                // SET LOCALES
                $iso_exp = Context::getContext()->language->iso_code;
                $iso_exp_BG = Context::getContext()->language->iso_code;

                switch ($iso_exp) {
                    case "ca":
                        $iso_exp_BG = "es";
                        break;

                    case "en":
                        $iso_exp_BG = "us";
                        break;
                }

                $iso_lang = $iso_exp . "_" . Tools::strtoupper($iso_exp_BG);

                setlocale(LC_TIME, $iso_lang . ".utf8");
                // END SET LOCALES

                if ($creneau->day != "0000-00-00" && $creneau->hour != "00:00:00") {
                    $pDrive = new PrestatillDrive();
                    $msg_creneau = $pDrive->getMsgCreneau($creneau);
                } else {
                    $msg_creneau = null;
                }

                $this->Cell(0, 10, $msg_creneau, 0, "C", true);
            }
        } else {
            return $this->Multicell(0, 0, "ORDER: $this->orderReference\n" . "$this->orderCustomer\n" . "$this->orderDateTime\n", 0, "C", false);
        }
    }

    public function Footer()
    {
        $this->SetFont("ticketing", "", 9);
        $this->SetX(22);
        return $this->Multicell(0, 0, "Page " . $this->getAliasNumPage() . "/" . $this->getAliasNbPages(), 0, "C", false);
    }
}
