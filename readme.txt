=== WP RESTful ===
Contributors: joseairosa
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=QMWD392MKS8UW&lc=PT&item_name=WordPress%20Plugin%20%28WP%20RESTful%29&item_number=WPRESTFUL&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donate_LG%2egif%3aNonHosted
Tags: api,rest,external communication,tokens,sharing,network,client,server
Requires at least: 2.9.2
Tested up to: 3.0
Stable tag: 0.1

The WP-RESTful is a REST API implementation for WordPress.

== Description ==

**What is it?**

I'm 99% sure that you already heard about APIs or REST APIs, it's what Twitter, flickr and a lot more companies use to share they're resources with a consumer.
A consumer can be anything from a website (for example, showing your Twitter updates on your blog or personal website) to a desktop / mobile application (iPhone, Android, Windows Mobile, ...).
This is possible because all information sent to and from the API is encoded in either two common, inter-platform language, XML and JSON.
The difference between a regular API and a REST API is on the way they work. A REST API requires two entities to work, a client and a server.



**How does it work?**

WP-RESTful uses OAuth, a widely, commonly and open source API implementation used all over the World (Wide Web).
The way it work is fairly simple.
First a Consumer registers with a Server. At this point the Server will provide the Consumer with 2 tokens, a public and a private token. The public token will be used to communicate and identify the Consumer with the Server while the private token will be stored locally for security and integrity reasons.
After this the Consumer will request the Server with a Request Token. This Request Token will be used to initiate the Authentication Protocol in where the Consumer will be required to authorize the Server. 
The Server will create 2 new token (definitive tokens). They will be our authentication tokens.
I know this sounds and seems complicated but you won't need to do anything as the Plugin will do almost everything for you (Registration and Authentication process are manual).



**Plugin features?**

* Add new Plugins to API Plugin to extend functionalities to any way you want/need. (See next group)
* Fully manageable Client and Server side.
* Ability to choose what fields are allowed to be returned to the consumer.
* Ability to restrict modules to OAuth authentication process or liberated them and make them open.
* Load balance system where you can specify how many requests a given consumer is allowed in a 60 minute timeframe.
* Out of the box Post and Comments management.
* And much more...



**Plugins**

As stated on "Plugin Features" you have the ability to develop and/or add new modules to your REST API. This means, for example, if you use a plugin like WP E-Commerce, you can develop a plugin for the REST API in order to provide support for WP E-Commerce resources.
The way these plugins are developed is very similar to how plugins for WordPress are developed.
You can see this video that explains how plugins work and how you can develop them.



**Requirements?**

All requirements for the plugin to work properly are addressed by the plugin itself, upon activation. You can see your system status on WP-RESTful link after activation the plugin.



== Installation ==

1. First you need to download it from WordPress Plugin Repository.
2. Upload the contents of the compacted file to your plugin folder on your WordPress installation.
3. Go to your WordPress Administration page and activate the Plugin (Plugins → Installed → WP-RESTful → Activate)
4. Create a new page, name it API, set the permalink to /api and set the content as [REST_return] and save the page.
5. Go to WP-RESTful → WP-RESTful to check your system status.

Adding Plugins

1. Download a plugin from a source bellow.
2. Upload the contents of the compacted file to your plugin folder on your WordPress installation.
3. Go to your WordPress Administration page and activate the Plugin (Plugins → Installed → (Plugin Name) → Activate)

Bellow are Plugins developed specifically for WP-RESTful:

[WP-RESTful Users Plugin](http://wordpress.org/extend/plugins/wp-restful-users-plugin/ "WP-RESTful Users Plugin")
[WP-RESTful Tags Plugin](http://wordpress.org/extend/plugins/wp-restful-tags-plugin/ "WP-RESTful Tags Plugin")
[WP-RESTful Categories Plugin](http://wordpress.org/extend/plugins/wp-restful-categories-plugin/ "WP-RESTful Categories Plugin")


== Frequently Asked Questions ==

= Is it secure =

It's very much secure. It uses the same API system as Twitter does and, as far as I know, they don't have that many problems :)

== Screenshots ==

1. Server configuration and consumer management.
2. Allowed information.
3. Registered consumers.
3. Servers listing and various steps.

== Changelog ==

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.1 =
* Initial release.

== Plugins ==

Bellow are Plugins developed specifically for WP-RESTful:

[WP-RESTful Users Plugin](http://wordpress.org/extend/plugins/wp-restful-users-plugin/ "WP-RESTful Users Plugin")
[WP-RESTful Tags Plugin](http://wordpress.org/extend/plugins/wp-restful-tags-plugin/ "WP-RESTful Tags Plugin")
[WP-RESTful Categories Plugin](http://wordpress.org/extend/plugins/wp-restful-categories-plugin/ "WP-RESTful Categories Plugin")

== Additional help ==

You can find additional information by visiting this plugin homepage: http://www.joseairosa.com/2010/06/29/wp-restful-wordpress-plugin/

== What's in the cooking pan? ==

If you have any features that you would like to see implemented, please don't hesitate to tweet me (http://twitter.com/joseairosa) or mail me (me@joseairosa.com) :)