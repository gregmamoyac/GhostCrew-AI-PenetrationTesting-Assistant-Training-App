# GhostCrew

GhostCrew is a web based terminal and AI chat assistant tool.

## Directory layout

```
assets/           Static assets used by the web UI
    css/          Stylesheets
    js/           Client side scripts
    img/          Images
admin/            Admin portal
api/              API endpoints
includes/         Helper libraries
local/            Scripts used by the Windows listener
```

## Running with XAMPP

1. **Install XAMPP**
   - Download the latest release from [Apache Friends](https://www.apachefriends.org/).
   - Run the installer and make sure Apache and MySQL components are installed.
2. **Start services**
   - Launch the *XAMPP Control Panel* and start both the Apache and MySQL services.
3. **Place GhostCrew in `htdocs`**
   - Clone or copy this repository to `C:/xampp/htdocs/GhostCrew`.
4. **Create the databases**
   - Open `http://localhost/phpmyadmin` in your browser.
   - Import `terminal_app.sql` and `ghostcrew_admin.sql` using the *Import* tab. This creates the required tables.
5. **Configure database credentials**
   - Edit `config.php` and `auth_config.php` if your MySQL credentials differ from the defaults. On a fresh XAMPP install the user is `root` with no password.
6. **Run the setup script**
   - Navigate to `http://localhost/GhostCrew/setup.php` and create the first admin account.
7. **Login**
   - Access `http://localhost/GhostCrew/login.php` with the credentials you created.

The application should now be running locally on your XAMPP stack.
