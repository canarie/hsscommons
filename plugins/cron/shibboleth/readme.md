To install this Plugin, please follow these steps:

1. Upload the entire content of this extension to the /yourhub/app/plugins/cron/shibboleth/ directory.
2. Run the "muse" command to add it:
	/yourhub/muse extension
	What do you want to do? [add|delete|install|enable|disable] add
	What extension were you wanting to add? plg_cron_shibboleth

To change the time period for the Shibboleth Session cleanup:

1. Log in to the administrative back-end of yourhub.
2. Navigate to Extensions -> Plug-in Manager, search for "Cron".
3. Click "Cron - Shibboleth" in the list.
4. In the Basic Options, set the value according to your need. The default value is 24 hours.
5. Save your change.

To set up a cron job for the Shibboleth Session cleanup:

1. Log in to the administrative back-end of yourhub.
2. Navigate to Components -> Cron.
3. Click the "+" icon at the top right corner to create a new cron job.
4. Set up the Title. 
5. From the Event list, select "Remove Shibboleth Sessions" underneath "Cron - Shibboleth".
6. Set up the Frenquency for the Recurrence.
7. Set the State to "Published" to enable this job.