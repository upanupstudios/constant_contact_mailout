# Constant Contact Mailout

Adds a field type to send email notifications to subscribers with Constant
Contact, and a block that lets visitors subscribe to contact lists.

Content editors send a mailout when they publish a node. Site builders map
contact lists to content through a field, and expose a subscribe form through
a block. Several Constant Contact accounts can be connected at once, so a
single subscribe form can sign a visitor up to lists that live in different
accounts.


## Table of contents

- Requirements
- Installation
  - Upgrading from a version before the administration pages moved
- Configuration
  - Create a Constant Contact application
  - Add a connection
  - Add the mailout field
  - Add the subscribe block
- Troubleshooting
- Maintainers


## Requirements

This module requires the following outside of Drupal core:

- [upanupstudios/constantcontact-php-client][client] (`^1.0`), installed
  automatically when the module is required through Composer.

A Constant Contact account with access to the
[My Applications portal][portal] is also required, one for each account that
is connected.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Modules][installing-modules].

```
composer require upanup/constant_contact_mailout
drush en constant_contact_mailout
```

### Upgrading from a version before the administration pages moved

The administration pages moved from `/admin/config/services/constant_contact_mailout`
to `/admin/config/constant_contact_mailout`. The authorize path is registered
as the **Redirect URI** on each Constant Contact application, and that is held
in the Constant Contact portal rather than in Drupal, so the update cannot make
this change for you.

After running database updates, update the Redirect URI of every connected
application to the new path before authorizing or re-authorizing a connection.
Running `drush updatedb` reports which connections are affected, and the same
message is written to the log. The exact URL to enter is shown when editing a
connection. See
[Create a Constant Contact application](#create-a-constant-contact-application)
below.


## Configuration

### Create a Constant Contact application

The module authenticates with the [OAuth2 Authorization Code Flow][server-flow],
which needs an application registered on the Constant Contact account. Repeat
these steps for each account that is connected.

1. Go to the [My Applications portal][portal] and create a new application.
2. Enter a name, for example `Drupal`.
3. Choose **Authorization Code Flow** and **Implicit Flow**.
4. Choose **Long Lived Refresh Tokens**.
5. Save, then edit the application and copy the **API Key**.
6. Click **Generate Client Secret** and copy the secret.
7. Set the **Redirect URI** to the authorize path on the site:
   `https://example.com/admin/config/constant_contact_mailout/connections/authorize`

For more detail, see [Create an application][apps-create].

### Add a connection

1. Grant the *Administer Constant Contact Mailout* permission to the roles that
   manage the integration, at Administration » People » Permissions.
2. Go to Administration » Configuration » Constant Contact Mailout »
   Connections and click **Add connection**.
3. Enter the name, API key and secret from the application, along with the
   sender information: from name, from email and reply-to email.
4. Save, then click **Authorize** on the connection and sign in to the Constant
   Contact account to grant access.

Once authorized, the connection's contact lists are stored with the connection.
The access token is refreshed on cron and again on demand whenever an API call
finds it expired or revoked, so cron should be running on the site.

Site-wide options live under the **Settings** tab: the default base URL used
when building links in email, a debug mode, on-screen rendering of the email
template when a node is published, and an option to redirect every mailing to a
single contact list for testing.

### Add the mailout field

1. Add a *Constant Contact Mailout* field to a content type at Administration »
   Structure » Content types » *Manage fields*.
2. In the field settings, set the email subject, which accepts node tokens, and
   choose how the contact lists are resolved:
   - **Dynamic** — send to a contact list created for each entity of this
     content type, using a connection and a contact list prefix. Lists are
     created when the entity is created; they are not deleted when the entity
     is deleted. When editing an existing entity, editors can tick *Change
     contact list to send mailout to* and pick another list from the
     connections; the new list is saved on the entity and used for this and
     later mailouts. Leave the box unticked to keep the current list.
   - **Select** — send to the contact lists selected for all entities of this
     content type.
   - **Taxonomy** — send to contact lists mapped from the terms of the selected
     vocabularies.
   - **Reference** — send to the contact lists of an entity reference from this
     content type.
3. Optionally allow editors to select the contact lists before sending.

When a node with the field is published, the mailout is sent to the resolved
contact lists.

Each mailout is created in Constant Contact as an email campaign named after
the subject and the time it was sent. Constant Contact limits campaign names to
80 characters, so a long subject is shortened in the campaign name. The subject
line the recipient sees is not affected.

### Add the subscribe block

1. Place the *Constant Contact Subscribe* block at Administration » Structure »
   Block layout.
2. Choose which fields the form collects, and whether visitors pick their own
   contact lists or are subscribed to a fixed set.
3. Optionally group the contact list checkboxes under labels, add a select-all
   checkbox, and set the messages shown after subscribing and unsubscribing.

A submission that spans several connections is reported to the visitor as a
single outcome. When a subscription fails, the visitor is asked to try again
later; the error returned by Constant Contact is not shown to them.
Per-connection failures are written to the log with the connection and contact
list names, along with the error.


## Troubleshooting

**A connection stops working, or the log reports that the access token could
not be refreshed.** Refresh tokens are rotated on every use, so a connection
whose refresh token is lost or invalidated has to be authorized again from the
Connections page.

**Subscribing fails without an obvious cause.** The API returns a generic error
message for several different conditions. The log records the error key
alongside the message, in square brackets, which identifies the actual
condition. Enable **Debug** under the Settings tab for more detail.

**The log reports "Email address is invalid" for an address that is valid.**
Constant Contact limits how often an address may attempt to subscribe, and
reports the temporary block that follows repeated signups with this message.
Wait before trying the address again. For the same reason a failed signup is
not retried automatically.

**Contact lists are missing or out of date.** Lists are cached with the
connection and refreshed when the access token is refreshed on cron. Run cron,
or re-authorize the connection.


## Maintainers

- Marco Maranao - [marcom@upanup.com](mailto:marcom@upanup.com)

[client]: https://github.com/upanupstudios/constantcontact-php-client
[portal]: https://app.constantcontact.com/pages/dma/portal/appList
[server-flow]: https://developer.constantcontact.com/api_guide/server_flow.html
[apps-create]: https://developer.constantcontact.com/api_guide/apps_create.html
[installing-modules]: https://www.drupal.org/docs/extending-drupal/installing-modules
