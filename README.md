# CEPI Member Management System

Een uitgebreid ledenbeheersysteem voor organisaties met privacy-focused email handling en import/export functionaliteit.

## 🚀 Features

- **Privacy-First Email Handling**: AES-256-GCM encryptie voor email adressen
- **Multi-Organization Support**: Beheer leden per organisatie
- **Import/Export**: Excel/CSV import en export functionaliteit
- **REST API**: Member verificatie API voor externe systemen
- **Admin Panel**: Volledig admin dashboard voor systeembeheer
- **Activity Logging**: Gedetailleerde logging van alle acties
- **Rate Limiting**: Beveiliging tegen misbruik

## 🛠️ Technologieën

- **PHP 8.2+**: Moderne PHP met type hints en attributes
- **MySQL/MariaDB**: Relationele database voor data opslag
- **Composer**: Dependency management
- **PhpSpreadsheet**: Excel bestand handling
- **HTML/CSS/JavaScript**: Frontend interface

## 📋 Vereisten

- PHP 8.2 of hoger
- MySQL 5.7+ / MariaDB 10.2+
- Composer
- Webserver (Apache/Nginx)

## ⚡ Installatie

1. **Clone de repository**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/cepi-member-management.git
   cd cepi-member-management
   ```

2. **Installeer dependencies**:
   ```bash
   composer install
   ```

3. **Configureer environment**:
   ```bash
   cp .env.example .env
   # Bewerk .env met je database credentials
   ```

4. **Database setup**:
   ```bash
   # Importeer basis schema
   mysql -u username -p database_name < database/schema.sql

   # Voer migraties uit (indien nodig)
   mysql -u username -p database_name < database/migration_hash_emails_safe.sql
   ```

5. **Webserver configuratie**:
   - Document root naar `public/` directory
   - Zorg voor URL rewriting voor nette URLs

## 🔧 Configuratie

### Environment Variables (.env)

```env
# Database
DB_HOST=localhost
DB_NAME=cepi
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_PORT=3306

# Application
APP_DEBUG=false
APP_URL=https://your-domain.com

# Email Encryption (VERPLICHT voor productie!)
EMAIL_ENCRYPTION_KEY=your-32-character-secret-key-here

# Upload settings
UPLOAD_MAX_SIZE=10485760
```

### Email Encryption Key

Voor productie moet je een sterke encryptie key instellen:

```bash
# Genereer een veilige key
openssl rand -hex 32
```

## 📖 Gebruik

### Admin Setup

1. Ga naar `/admin/login.php`
2. Log in met admin credentials
3. Maak organisaties aan
4. Stel API keys in (indien nodig)

### Organisatie Login

1. Ga naar `/login.php`
2. Log in met organisatie credentials
3. Import/export leden via dashboard

### API Gebruik

```bash
# Controleer of email lid is
GET /api/check-member.php?email=user@example.com

# Response
{
  "found": true,
  "mm_cepi": true,
  "organisation_id": 1,
  "organisation_name": "Example Org"
}
```

## 🔒 Beveiliging

- **CSRF Protection**: Alle forms beschermd tegen CSRF attacks
- **Input Validation**: Uitgebreide input sanitization
- **Rate Limiting**: API bescherming tegen misbruik
- **Email Encryption**: Privacy bescherming volgens GDPR
- **SQL Injection Prevention**: Prepared statements overal

## 📊 Database Schema

- `organisations`: Organisatie informatie
- `members`: Lid gegevens (gehasht)
- `organisation_auth`: Authenticatie voor organisaties
- `import_logs`: Import historie
- `activity_logs`: Systeem activiteit logging

## 🚀 Deployment

### Productie Checklist

- [ ] `.env` bestand correct geconfigureerd
- [ ] `EMAIL_ENCRYPTION_KEY` ingesteld
- [ ] Database gemigreerd naar hashing schema
- [ ] Webserver SSL/TLS geconfigureerd
- [ ] File permissions correct ingesteld
- [ ] Error logging geconfigureerd
- [ ] Backupsysteem actief

### Docker (Optioneel)

```dockerfile
FROM php:8.2-apache
COPY . /var/www/html/
RUN composer install --no-dev --optimize-autoloader
EXPOSE 80
```

## 🤝 Bijdragen

1. Fork het project
2. Maak een feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit je changes (`git commit -m 'Add some AmazingFeature'`)
4. Push naar branch (`git push origin feature/AmazingFeature`)
5. Open een Pull Request

## 📝 Licentie

Dit project is gelicenseerd onder de MIT License - zie het [LICENSE](LICENSE) bestand voor details.

## 🆘 Support

Voor vragen en support:
- Controleer de [API Documentation](api/API_DOCUMENTATION.md)
- Bekijk de [Installatie Handleiding](database/INSTALLATION.md)
- Open een GitHub issue voor bugs

## 🔄 Roadmap

- [ ] Docker containerisatie
- [ ] Unit tests toevoegen
- [ ] Email notificaties
- [ ] Bulk operaties optimaliseren
- [ ] Real-time import status
- [ ] Multi-language support

---

**CEPI Member Management System** - Bouw voor de toekomst, bescherm de privacy.
