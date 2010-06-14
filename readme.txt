=== CommunityAPI SSO ===
Contributors: andsten
Tags: sso, authentication, single signon, single sign-on, provisioning, phpbb, phpbb3, api
Requires at least: 2.8.6
Tested up to: 3.0
Stable tag: 0.98

CommunityAPI SSO provides user provisioning, authorization and single signon capabilities against CommunityAPI providers.

== Description ==

CommunityAPI is a lightweight Single Sign On and directory service. The following capabilities are supported:

* Single Sign On via a known identity provider supporting CommunityAPI calls.
* Password proxy support, verifying a submitted user/pass by sending them to the identity provider.
* Auto provisioning of users, should they not exist in the Wordpress database. 
* Authorization set by group membership in the identity provider. 

The provider and consumer (wordpress) does not have to reside on the same host.

To examplify: If you have a phpBB3 forum with the CommuntiyAPI phpBB3 plugin, installing this plugin will allow you to 
login to wordpress using the phpBB3 credentials, will log you in automatically as long as you have a phpBB3 session going 
and will create the user automatically if it doesn't already exist locally in Wordpress. Group memberships in phpBB can be 
tied to access levels in Wordpress, in which case the access level of the user is modified accordingly each login.

As such, you'd really want to operate both the provider (phpBB3 or similar) and the consumer (Wordpress) under the same 
administative 'domain', since Wordpress as a whole needs to trust the provider. 

Local wordpress users are supported, but keep in mind that a CommunityAPI user with the same username *will* be seen as 
the same user.

== Installation ==

1. Upload `coapi.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Setup the plugin from CommunityAPI item in the Settings menu in the WP Admin.

* "API url" should contain the exact URL to the CommunityAPI provider script. For phpBB, this would be http://yourforum/api.php rather than http://yourforum/
* "API secret" is the shared secret between the Wordpress and the CommunityAPI provider. Make this long and complicated. Should be considered a secret.
* "API site" is the 'username' of this CommunityAPI consumer. Used in conjunction with the API secret to authenticate the Wordpress system to the CommunityAPI provider.
* "Admin group" should contain the name of the CommunityAPI provider group whose members should become Wordpress administrators.
* "Editor group" should contain the name of the CommunityAPI provider group whose members should become Wordpress editors.
* "Subscriber group" should contain the name of the CommunityAPI provider group whose members should become Wordpress subscribers.

== Frequently Asked Questions ==

= How is this different from OpenID? =

OpenID is an excellent solution for cases where you need Single Sign On and you might need it across services operated 
by different entities. However, it requires the user to know a bit about how the system works and it won't provide authorization. 
It also operates on a user-by-user basis, while CommuntiyAPI operates on the entire application.

= How is this different from LDAP? =

LDAP is a source of authentication and authorization information. It doesn't (by itself) provide single signon.
CommunityAPI is, first and foremost, a simple way of implementing single signon. The source of the authentication 
data could well be an LDAP server in the back end of things.

= What about password security? =

This plugin communicates with the CommunityAPI provider via HTTP or HTTPS (the latter being as secure as the PHP CURL library 
makes it). If password proxying is used, Wordpress sees but doesn't store the user password. 

= What about code security? =

The size of the plugin allows for a fairly simple security audit of the code, should one feel so inclined. Note, however, that 
*it has not been verified by a third party* thus far. You have been warned. 

= What about network security? =

Replay attacks between the consumer and the provider are definitely possible and man-in-the-middle can ruin the day. Wrap the traffic in SSL if this is an issue for you.

== Changelog ==

= 1.0 =
Changed the name of the plugin (the old name, wpcom, wasn't approved for a wordpress plugin).
Initial checkin at the Wordpress provided repo. 
