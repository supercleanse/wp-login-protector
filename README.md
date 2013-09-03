WP Login Protector
==================

Simple tools to protect your wordpress login from brute force attacks.

## POST Cookie Protection
This will set a cookie when an initial, GET request is made on the site (which happens when a human logs in).

If the Cookie is not present on the POST request then the login is blocked.

This effectively blocks non-human robots from successfully issuing a POST request to WordPress' login page.

## Block HTTP/1.0 POSTs
Block any login POST requests made with HTTP 1.0. Since it is common for bots to use HTTP 1.0, this should effectively block them from attempting to login.

## Basic Authentication
This will add an extra layer of basic authentication to the WordPress login page.

This is a more aggressive approach but should completely prevent any bots from even attempting a wordpress login.

Unlike modifying your webserver configuration to add Basic Authentication, this approach will not break the functionality of nopriv ajax actions.

