TODO:

Something wrong with this one when trying to connect or refresh
Need to refresh token when expired somehow



// @todo Refresh connections and contact lists in cron.

Try to add this module through composer from private GitHub

Use cron to refresh the API.

The site should always be connected online, but should be handled if unable to connect to wrap in a try/catch.

Field Config
Change taxonomies to list of vocabulary checkboxes
Hide/show list of terms from selected vocabularies

Field Widget
If dynamic, add description
If taxonomy, add description
If select, add description.
If select before sending, add description and show list
Add send now description

Subscription Block
Option to use entity mailout field settings, this would only work if set to select list is off.
Option which fields to enable, first name, last name, email, confirm email, consent checkbox?
If not, choose list(s) to added to.


Need to replicate multiple account list sign-up functionality
https://subscribe.wellington.ca/Subscribe

Constant Contact My Applications
https://app.constantcontact.com/pages/dma/portal/


Uses OAuth2 Authorization Code Flow
https://developer.constantcontact.com/api_guide/server_flow.html

App Configuration
https://developer.constantcontact.com/api_guide/apps_create.html
Go to https://app.constantcontact.com/pages/dma/portal/appList
Create a New Application
Enter name (ie. Drupal)
Choose Authorization Code Flow and Implicit Flow.
Choose Long Lived Refresh Tokens (for now)
Click Create

Edit the newly created application
Copy the API Key
Click Generate Client Secret and copy secret
Edit Redirect URI: http://wellingtoncounty935.upanupstudios.local/admin/config/constant_contact/authorization
Tab to OAuth2 to change refreshing tokens
Tab to Desription and enter description
Click Save


Module Configuration
Authorize


HOW
Create app for account, enter return URL
Create a token for each account
Where to store token?
Create block with contact info and lists, group by same account
Custom block submit handler to POST contact data to different selected lists in different accounts

KNOWN
Lists are found across 6 accounts
Only 1 account contains the E-newsletter lists
Use Create or Update a Contact Using a Single Method in API https://developer.constantcontact.com/api_guide/contacts_create_or_update.html

NEED
Login credentials to all accounts to create access
Create Drupal application for each account
Create tokens (no expiry) for each account to use API
