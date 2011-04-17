=============================================
What to know to write a plugin for WP-RESTful
=============================================

Installation
------------
Installation of a WP-RESTful plugin is the same as a standard WordPress plugin
because it is a WP plugin with some specific calls to register with WP-RESTful.

Writing A WP-RESTful Plugin
---------------------------
Writing a plugin for WP-RESTful requires writing a class to do the work and
registering the plugin with WP-RESTful. WP-RESTful handles the routing that is
to be expected with working with a RESTful service. For information on this,
take a look at the microformat_.

WP-RESTful operates as a read-only service provider; a plugin's "get" methods
are all that are called. Functions are not currently handled differently by
different HTTP methods.

Plug Into WP-RESTful
--------------------

1.	Plugin should install itself on activation and uninstall itself on
	deactivation.
1.1	Installation includes add plugin name to active plugins list:
::
	function wpr_myplugin_install() {
		// get a handle to the active plugins
		$wpr_plugins = get_option("wpr_plugins");
		if(!is_array($wpr_plugins))
			$wpr_plugins = array();
		
		// Add our plugin as active
		$wpr_plugins['plugin_name'] = "my-plugin-dir";
		update_option("wpr_plugins",$wpr_plugins);
	}
	register_activation_hook(WP_PLUGIN_DIR.'my-plugin-dir/my-plugin.php', 'wpr_myplugin_install');

1.2	Uninstallation includes removing plugin name from active plugins list:
::
	function wpr_myplugin_uninstall() {
		// get a handle to the active plugins
		$wpr_plugins = get_option("wpr_plugins");
		if(!is_array($wpr_plugins))
			$wpr_plugins = array();
		
		// Remove this plugin as active
		$wpr_active_plugins = array_diff($wpr_plugins,array("my-plugin-dir"));
		update_option("wpr_plugins",$wpr_active_plugins);
	}
	register_deactivation_hook(WP_PLUGIN_DIR.'my-plugin-dir/my-plugin.php', 'wpr_myplugin_uninstall');

2.	Register with WP-RESTful by calling ``wpr_add_plugin`` and passing it a method that will describe your admin entry:
::
	function wpr_myplugin_fields() {
		return array('My Plugin' => array(
			'thing_ID' => 'Thing ID',
			'name' => 'Thing Name',
			'description' => 'Thing Description',
			'parent' => 'Thing Parent (ID)',
			'count' => 'Thing Usage Count',
			'slug' => 'Thing Slug (nice name)'
		));
	}
	wpr_add_plugin('wpr_myplugin_fields');

3.	Register your plugins pluralization if applicable:
::
	function wpr_myplugin_pluralization() {
		// this is the default case and can be omitted if this follows your
		// entity's pluralization
		//return array('myplugin' => 'myplugins');
		
		// example using an irregular noun
		return array('category' => 'categories');
	}
	wpr_add_pluralization('wpr_myplugin_pluralization');

Extend WPAPIRESTController
--------------------------
The work horse of all this will be a class that extends the REST controller base
class. All REST controllers will extend a provided base controller and the name
should start with the singular form of your entity's name.
::
	class PersonRESTController extends WPAPIRESTController {

There are 2 cases for reaching your REST controller: by its singular form and by
its plural form. You will have a method for each of these.
::
	protected function getPeople() { }
	
	protected function getPerson($person) { }
	
	// this is accessed by /api/person/carl.json
	protected function getCarl() { }

If http://localhost/wp/api/person/carl.json is accessed, ``getCarl()`` is called.
If http://localhost/wp/api/person/bob.json is accessed, getPerson('bob') is
called.

Method lookup happens in the followingorder (note: actionRequest is the ID part
of the URL):

1. If actionRequest == all, getPlural [``getPeople``]
2. If class has function named ``'get' + actionRequest``, call ``get$actionRequest`` [``getCarl``]
3. Call getSingular and pass actionRequest as parameter. [``getPerson('bob')``]

Accessing Your Controller
-------------------------
Assuming you have WP-RESTful set to work at /api, you would access the above
controller using a URL like this:

	http://localhost/wp/api/people.json

which will make a call to ``PersonRESTController.getPeople()``.

To get a specific record you would use a URL like this:

	http://localhost/wp/api/person/45.json

where '45' is the ID used in your system and can be of any form. Further
parameters can be passed as part of the querystring in the usual key=val form.

	http://localhost/wp/api/country/France.json?city=Paris

Note the use of singular and plural forms depending on if all people or a single
person is expected to be returned. This is used by WP-RESTful to look up the
method to handle the request.

Controller Name Definition
--------------------------
The check order for REST controller classes by name is:

1. MyEntityRESTController.php
2. lib/MyEntityRESTController.php
3. <check each loaded plugin's lib dir>/MyEntityRESTController.php
4. MyEntity.php
5. lib/MyEntity.php
6. <check each loaded plugin's lib dir>/MyEntity.php
7. MyEntities.php
8. lib/MyEntities.php
9. <check each loaded plugin's lib dir>/MyEntities.php

.. _microformat: http://microformats.org/wiki/rest