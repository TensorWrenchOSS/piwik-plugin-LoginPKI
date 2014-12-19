# Piwik LoginPKI Plugin

## Description

This plugin will add the following features to a piwik installation:

 * Automatic login with verification against authorization service
 * Defined admin and non-admin roles
 * Default site for new users to view


LoginPKI plugin will require deployment of the ozone-enhancements branch of piwik as well as the activation of 
the ClientCertificates plugin.

## Installation

* Clone plugin repo into `piwik/plugins/` directory 
```
cd piwik/plugins
git clone <git-url> LoginPKI
```

* Add configuration to `config/config.ini.php`
```
[LoginPKI]
superusers[] = "admin"
superusers[] = "<additional-admin-user>"
...
default_site_id = 1
```
* Activate plugin by going to web interface, Settings --> Plugins, and then Activate the LoginPKI plugin.


Now clients of a Piwik enabled application should be enabled with client certificates.