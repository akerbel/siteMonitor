siteMonitor
===========

A daemon for monitoring of a site status.

Parameters:
- l - a monitored link
- s - custom settings

Example:
```
# Simple start
php siteMonitor.php -l "https://www.google.com"

# Start with custom settings(put your custom settings file into the settings folder)
php siteMonitor.php -l "https://www.google.com" -s"mysettings.json"
```

Settings
--------
The settings are a json file inside settings/ folder. A default settings file is default.json.

```
{
  // A delay between checks
  "delay": 60
  ,
  // A name of a message transport class
  "message_transport": "Mail"
  ,
  // Addresses
  "emails": [
    "null@gmail.com"
  ]
  ,
  // Sender name
  "from": "siteMonitor@null.domain"
  ,
  // Time intervals between error messages.
  // Count of intervals determines count of messages.
  "time":[
    180,
    600,
    3000,
    6000,
    30000
  ]
}
```