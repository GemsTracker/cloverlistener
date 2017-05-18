# CloverListener
A listener service for Cloverleaf MLLP HL7 2.4 messages that allows for integration with GemsTracker

This service is still a work in progress. When finished it will allow integration of GemsTracker with a MLLP HL7 broadcast service like Cloverleaf.

## Installation
To install download a zip of the [latest release](https://github.com/GemsTracker/cloverlistener/releases/latest) and use [composer](https://getcomposer.org/) to get the needed dependencies.

1. Run composer install

   ```bash
   $ composer install --no-dev
   ```
2. Edit config/config.php
3. Copy config/db.inc.php.dist to /config/db.inc.php and adjust database settings
4. Run cloverlistener.php with option install

   ```bash
   $ php cloverlistener.php install
   ```
   
You are now ready to run the service in the background, for example using a simple upstart script.

## Command line usage
<pre>
Usage: cloverlistener.php [ options ]
 autocomplete  Command autocompletion setup
 help          Get help for individual commands
 install       Install the application.
 listen        Listen to HL7 messages.
 queue         Manipulate the message queue
 version       Display the version of the script
</pre>

Most common usage is to listen and execute the message in one go. You can achieve this by using:
```bash
$ php cloverlistener.php listen
```

When you want to have two instances, one to listen and place messages in the queue and one to process the queue you need to run the following commands, each in their own process:
```bash
$ php cloverlistener.php listen norun
$ php cloverlistener.php queue run
```