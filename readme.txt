=== Post from Email ===
Author URI: https://github.com/OllieJones
Plugin URI: https://www.plumislandmedia.net/post-from-email/
Donate link: 
Contributors:  Ollie Jones
Tags: private
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.5.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Creates posts on your site from emails, including your own, and email blasts sent by Constant Contact and other services.

== Description ==

Create posts from email messages.

Thanks to Jetbrains for the use of their software development tools, especially [PhpStorm](https://www.jetbrains.com/phpstorm/). It's hard to imagine how a plugin like this one could be developed without PhpStorm's tools for exploring epic code bases like WordPress's.

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.


== Installation ==

1. Go to `Plugins` in the Admin menu
2. Click on the button `Add new`
3. Click on Upload Plugin
4. Find `post-from-email.zip` and upload it
4. Click on `Activate plugin`

If you want to post emails when they're sent out, POST your them to the REST service provided by this plugin `/wp-json/post-from-email/v1/upload` on your site.

You can use the [CloudMailin](https://cloudmailin.com/) service to create a special-purpose email address. Then you can put that email address on your distribution list.

== Changelog ==

= 0.5.1: June 14, 2023
Ingest images to media library, show html directly rather than in iframes, php8 compatibility.

= 0.4.1: May 16, 2023
Handle profile id better, handle subdirectory installations, fix minor bugs.

= 0.3.6: May 16, 2023
Allow profile id in upload REST route.

= 0.3.5: April 12, 2023
Choice of email-check frequency.

= 0.3.4: April 11, 2023
Log activity and add activity metabox.

= 0.3.3: April 8, 2023
Add simple control of posting from a webhook.

= 0.3.2: April 6, 2023
Keep / Remove setting, squash mojibake.

= 0.3.0: April 3, 2023
POP3 fetching, Templates

= 0.2.1: March 25, 2023
Add setting of categories from email+category|category|category@example.com on the To addess.

= 0.1.0: March 20, 2023 =
* Birthday of Post from Email Address
