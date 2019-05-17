# WHMCS FicoraEPP

A Ficora EPP domain registrar module for WHMCS

## Requirements

* PHP 7.1 with PHP-APCU and Composer
* Ficora EPP Account (or EPP Test Account)

## Installation

* Clone or download zip & extract zip to <your WHMCS installation>/modules/registrars/ficoraepp
* Add additional fields from resources folder to <your WHMCS installation>/resources/domains/additionalfields.php
* Install via composer requirements: ```composer install --no-dev``` on your <your WHMCS installation> dir.
* Activate Plugin on WHMCS Setup -> Addon Modules

```
cd modules/registrars/
git clone https://github.com/tssge/whmcs-ficoraepp.git ficoraepp
cd ficoraepp
composer install --no-dev
```

## Configuration

* On Ficora EPP Account you must allow your server IP-address where WHMCS installed.
* Generate Certificate for authentication. Private Key must have password. Upload public certificate (cert.pem) to Ficora EPP Account. 

```
openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem -days 7200
cat cert key >> epp.pem
```

* Fill API Username, API Password, Tech Contact, Certificate Path (epp.pem) and Certificate Password to WHMCS Setup -> Product & Services -> Domain Registerar -> Ficora Epp.

## Debug

* Enable Debug-logging on WHMCS HMCS Setup -> Product & Services -> Domain Registerar -> Ficora Epp and tick Enable debug mode. This will write debug log to <your WHMCS installation>/debug.txt
