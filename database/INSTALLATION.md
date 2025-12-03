# Database Installatie Instructies

## Stap-voor-stap Database Setup

### Stap 1: Basis Schema Installeren

Voer eerst het hoofd schema uit:

```bash
mysql -u your_username -p < database/schema.sql
```

Of via HeidiSQL/phpMyAdmin:
1. Open `database/schema.sql`
2. Voer het volledige bestand uit

Dit maakt de volgende tabellen aan:
- `organisations` - Organisaties
- `members` - Leden
- `organisation_auth` - Organisatie authenticatie
- `import_logs` - Import geschiedenis

### Stap 2: Admin Systeem Installeren

Voer daarna de admin migratie uit:

```bash
mysql -u your_username -p < database/migration_admin_system.sql
```

Of via HeidiSQL/phpMyAdmin:
1. Open `database/migration_admin_system.sql`
2. Voer het volledige bestand uit

Dit maakt de volgende tabellen aan:
- `admin_users` - Admin gebruikers
- `activity_logs` - Activity logging

En maakt een standaard admin account aan:
- **Gebruikersnaam:** `admin`
- **Wachtwoord:** `admin123`
- **⚠️ BELANGRIJK:** Verander dit wachtwoord direct na installatie!

### Stap 3: Verifieer Installatie

Controleer of alle tabellen zijn aangemaakt:

```sql
USE cepi;
SHOW TABLES;
```

Je zou de volgende tabellen moeten zien:
1. ✅ `admin_users`
2. ✅ `activity_logs`
3. ✅ `import_logs`
4. ✅ `members`
5. ✅ `organisation_auth`
6. ✅ `organisations`

### Stap 4: Test Admin Login

1. Ga naar: `http://your-domain.com/public/admin/login.php`
2. Log in met:
   - Gebruikersnaam: `admin`
   - Wachtwoord: `admin123`
3. **Verander direct het wachtwoord** via de admin interface (als deze functionaliteit beschikbaar is)

## Database Structuur Overzicht

### Admin Systeem
- **admin_users**: Admin gebruikers accounts
- **activity_logs**: Alle activiteiten (API calls, logins, uploads)

### Organisatie Systeem
- **organisations**: Organisaties
- **organisation_auth**: Organisatie login accounts
- **members**: Leden per organisatie
- **import_logs**: Import geschiedenis

## Troubleshooting

### Fout: "Table already exists"
Als je een fout krijgt dat een tabel al bestaat, betekent dit dat je de migratie al hebt uitgevoerd. Dit is geen probleem - de `CREATE TABLE IF NOT EXISTS` statements zorgen ervoor dat bestaande tabellen niet worden overschreven.

### Fout: "Unknown database 'cepi'"
Zorg ervoor dat je eerst `schema.sql` hebt uitgevoerd, want dit maakt de database aan.

### Admin account werkt niet
1. Controleer of de `admin_users` tabel bestaat
2. Controleer of er een record is met username 'admin'
3. Probeer het wachtwoord opnieuw te hashen:
   ```php
   php -r "echo password_hash('jouw_wachtwoord', PASSWORD_DEFAULT);"
   ```
4. Update de hash in de database:
   ```sql
   UPDATE admin_users SET password_hash = 'jouw_nieuwe_hash' WHERE username = 'admin';
   ```

## Volgende Stappen

Na de database installatie:
1. ✅ Configureer `.env` bestand met database credentials
2. ✅ Test de database connectie
3. ✅ Log in op het admin dashboard
4. ✅ Verander het standaard admin wachtwoord
5. ✅ Maak organisatie accounts aan via het admin dashboard

