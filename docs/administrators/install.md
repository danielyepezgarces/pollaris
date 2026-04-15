# Install in production

This document explains how to install Pollaris on a server.

In this documentation, it is expected that you're at ease with managing a webserver with PHP.
Nginx is used as the webserver in this documentation.
Apache should work as well, but it hasn't been tested.

## Installation

### Check the requirements

Check Git is installed:

```console
$ git --version
git version 2.47.0
```

Check PHP version (must be >= 8.2):

```console
$ php --version
PHP 8.2.25 ...
```

Check your database version (must be PostgreSQL >= 15):

```console
$ psql --version
psql (PostgreSQL) 15.8
```

Check [Composer](https://getcomposer.org/) is installed:

```console
$ composer --version
Composer version 2.8.1 2024-10-04 11:31:01
```

### Create the database

Create a dedicated user and database for the application:

```command
# sudo -u postgres psql
postgres=# CREATE DATABASE pollaris_production;
postgres=# CREATE USER pollaris_user WITH ENCRYPTED PASSWORD 'secret';
postgres=# GRANT ALL PRIVILEGES ON DATABASE pollaris_production TO pollaris_user;
postgres=# ALTER DATABASE pollaris_production OWNER TO pollaris_user;
```

### Download the code

You may want to put the application under a specific folder on your server, for instance:

```console
$ cd /var/www/
```

Clone the code:

```console
$ git clone https://framagit.org/pollaris/pollaris.git
$ cd pollaris
```

### About file permissions

You’ll have to make sure that the system user that runs the webserver can access the files under the application directory.
This user is often `www-data`, `apache` or `nginx`.
In this documentation, we’ll use `www-data` because it is the most generic name.

Set the owner of the files to the user that runs your webserver:

```console
$ sudo chown -R www-data:www-data .
```

From now on, you must execute the commands as the user `www-data`.
You can either start a shell for this user (to execute as root):

```console
# su www-data -s /bin/bash
www-data$ cd /var/www/pollaris
```

Or prefix **all** the commands with `sudo -u www-data`.
For instance:

```console
$ sudo -u www-data php bin/console
```

If your current user is not in the sudoers list, you’ll need to execute the `sudo` commands as `root`.

The commands that need to be executed as `www-data` **will be prefixed by `www-data$` instead of simply `$` in the rest of the documentation.**

### Switch to the latest version

Checkout the code to the latest version of the application:

```
www-data$ git checkout $(git describe --tags $(git rev-list --tags --max-count=1))
```

[The full list of releases.](https://framagit.org/pollaris/pollaris/-/releases)

### Check the PHP extensions

Check that the PHP extensions are installed:

```console
$ composer check-platform-reqs
Checking platform requirements for packages in the vendor dir
...
php           8.2.25     success
```

If requirements are not met, you’ll have to install the missing extensions.

### Configure the application

Create a `.env.local` file:

```console
www-data$ cp env.sample .env.local
```

And edit the variables to your needs.
The file is commented to help you to change it.

**Restrict the permissions on this file:**

```console
www-data$ chmod 400 .env.local
```

### Install the dependencies

Install the Composer dependencies:

```console
www-data$ composer install --no-dev --optimize-autoloader
```

You don't need to install the NPM dependencies because the assets are already pre-built for production.

### Setup the database

Initialize the database:

```console
www-data$ php bin/console doctrine:migrations:migrate --no-interaction
```

### Configure the webserver

Configure your webserver to serve the application.
With Nginx:

```nginx
server {
    server_name pollaris.example.org;
    root /var/www/pollaris/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;

        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;

        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    error_log /var/log/nginx/pollaris_error.log;
    access_log /var/log/nginx/pollaris_access.log;
}
```

Check the configuration is correct:

```console
$ nginx -t
```

And reload Nginx:

```console
$ systemctl reload nginx
```

Open Pollaris in your web browser: you should see the home page.

### Setup the Messenger worker

The Messenger worker performs asynchronous jobs.
It's a sort of Cron mechanism on steroids.
We'll use Systemd in this documentation, but note that the only requirement is that a command needs to run in the background.

Create the file `/etc/systemd/system/pollaris-worker.service`:

```systemd
[Unit]
Description=The Messenger worker for Pollaris

[Service]
ExecStart=php /var/www/pollaris/bin/console messenger:consume async scheduler_default --time-limit=3600

User=www-data
Group=www-data

Restart=always
RestartSec=30

[Install]
WantedBy=default.target
```

Enable and start the service:

```console
# systemctl enable pollaris-worker
# systemctl start pollaris-worker
```

You can read the logs with:

```console
# journalctl -f -u pollaris-worker@service
```

### Optional: Create an admin

You can create an admin by running the following command:

```console
www-data$ php bin/console app:user:create
```

Open the login page at `https://pollaris.example.org/login`.

The administration allows you to search polls by <abbr>URL</abbr>, title, author name, or email.

### Optional: Customise your platform

There are several ways in which you can customise Pollaris to suit your visitors' needs.

First of all, you can change the name of the platform by setting the `APP_NAME` variable in the `.env.local` file.

You also can create the following files:

- `public/custom.css`: custom CSS rules;
- `public/custom.js`: a custom JS script;
- `templates/home/custom.html.twig`: allows to customise the home page (you can copy the [`templates/home/show.html.twig`](/templates/home/show.html.twig) template to get started).

### Optional: Installing behind a reverse proxy

If your instance of Pollaris is behind a reverse proxy, you may need to do additionnal work.
In particular, if the URLs generated in Pollaris are in HTTP, you may need to tell your Web server that the service is served over HTTPS.

[Read more in the Symfony documentation.](https://symfony.com/doc/current/deployment/proxies.html#overriding-configuration-behind-hidden-ssl-termination)

## Updating the production environment

**Please always start by checking the migration notes in [the changelog](/CHANGELOG.md) before updating the application.**

Remember that commands prefixed by `www-data$` need to be run as the `www-data` user.
[Read more about file permissions.](#about-file-permissions)

Pull the changes with Git:

```console
www-data$ git fetch
```

Switch to the latest version:

```console
www-data$ git checkout $(git describe --tags $(git rev-list --tags --max-count=1))
```

Install the new/updated dependencies:

```console
www-data$ composer install --no-dev --optimize-autoloader
```

Execute the migrations:

```console
www-data$ php bin/console doctrine:migrations:migrate --no-interaction
```

Clear the cache:

```console
www-data$ php bin/console cache:clear
```

Restart the Systemd service:

```console
# systemctl restart pollaris-worker
```
