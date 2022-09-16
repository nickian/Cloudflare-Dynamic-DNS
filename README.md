# Cloudflare Dynamic DNS

A PHP script that checks for changes to your public IP and updates a Cloudflare "A" record with your new IP address when it detects a change.

## Setup

### Install Dependencies

You should have [Composer](https://getcomposer.org) installed.

```bash
composer install
```

### Create a Token

Create an API token: https://dash.cloudflare.com/profile/api-tokens

Create an API token with permissions to edit DNS records for the zone you wish to use.

### Copy and Update the `config.template.php` File.

```bash
cp config.template.php config.php
```

Add your TOKEN value and add SMTP info, if you want to enable email notifications about IP changes.

### Run the Script

```bash
php cli.php
```

Run the script yourself from the command line the first time. You will be prompted to define the zone name and record name. The script will obtain your current public IP and get the zone and record IDs. If a record does not exist with the name you provided, one will be created.

Each time script runs again, it will obtain your current public IP and compare it with the last value saved in the JSON file. If it detects a change, it will update the DNS record.

### Add a recurring cron job

Add the script to a cron job to automatically check for IP changes at a given interval.

Edit cron jobs for current user: `crontab -e`

or for a specific user `sudo crontab -u user -e`

Paste and edit the line below to suit your needs. This runs the script every hour and stores the output in the log file in the same directory.

```
0 */1 * * * /usr/bin/php -f /path/to/Cloudflare-Dynamic-DNS/cli.php > /path/to/Cloudflare-Dynamic-DNS/log
```