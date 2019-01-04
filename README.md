# WHMCS FicoraEPP

A Ficora EPP domain registrar module for WHMCS

## Requirements

* PHP7.2 and Composer
* Ficora EPP Account (or Test Account)

## Installation

* Clone or download zip
* Extract or save to folder <your WHMCS installation>/modules/registrars/ficoraepp
* Add additional fields from resources folder to <your WHMCS installation>/resources/domains/additionalfields.php
* Install via composer requirements: ```composer install --no-dev``` on your <your WHMCS installation> dir.
* Activate Plugin on WHMCS Setup -> Addon Modules

## Configuration

* On Ficora you must allow your WHMCS IP-address. 
* Generate Certificate for authentication. Private Key must have password. Upload public certificate (cert.pem) to Ficora. 

```
openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem
cat cert key > epp.pem
```

* Fill API Username, API Password, Tech Contact, Certificate Path (epp.pem) and Certificate Password to WHMCS Setup -> Product & Services -> Domain Registerar -> Ficora Epp.

## Debug

* Enable Debug-logging on WHMCS HMCS Setup -> Product & Services -> Domain Registerar -> Ficora Epp and tick Enable debug mode. This will write debug log to <your WHMCS installation>/admin/debug.txt
