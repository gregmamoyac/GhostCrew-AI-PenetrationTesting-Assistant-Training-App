# GhostCrew

GhostCrew is a web-based terminal and AI chat assistant tool.

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
4. **Create the SQL user accounts**
   - Open PHPMyAdmin at `http://localhost/phpmyadmin`.
   - Go to the **User accounts** tab.
   - Click **Add user account**.
   - Enter a username (e.g., `ghostcrew`) and a strong password.
   - For **Host name**, select `localhost`.
   - Under **Database for user account**, select **Grant all privileges on database**.
   - Alternatively, you can create a separate user for each database if you prefer.
   - Click **Go** to create the user(s) and assign privileges.
5. **Run the setup script**
   - Navigate to `http://localhost/GhostCrew/install.php` and follow the instructions.
6. **Login**
   - Access `http://localhost/GhostCrew/index.php` with the credentials you created.

The application should now be running locally on your XAMPP stack.