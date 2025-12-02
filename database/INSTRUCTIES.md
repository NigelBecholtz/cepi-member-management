# Database Setup Instructies voor HeidiSQL

## Stap 1: Maak verbinding met je database server

1. Open HeidiSQL
2. Maak een nieuwe sessie aan (of gebruik je bestaande "cepi" sessie)
3. Verbind met:
   - **Hostname/IP:** 127.0.0.1 (of je externe server)
   - **User:** root
   - **Password:** (jouw wachtwoord, indien ingesteld)
   - **Port:** 3306

## Stap 2: Maak de database aan

**Optie A: Via HeidiSQL Interface**
1. Klik met rechts op je server verbinding in het linker paneel
2. Selecteer "Create new" > "Database"
3. Geef de database de naam: `cepi`
4. Character set: `utf8mb4`
5. Collation: `utf8mb4_unicode_ci`
6. Klik op "OK"

**Optie B: Via SQL Query**
1. Open een nieuwe query tabblad in HeidiSQL
2. Kopieer en plak deze SQL:

```sql
CREATE DATABASE IF NOT EXISTS cepi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Klik op "Execute" (F9)

## Stap 3: Selecteer de database

1. In het linker paneel, klik op de database `cepi` om deze te selecteren
2. Of voer in een query tabblad uit: `USE cepi;`

## Stap 4: Importeer het schema

**Optie A: Via HeidiSQL File Menu**
1. Ga naar **File** > **Load SQL file...**
2. Navigeer naar: `C:\Users\GAMING\Desktop\Stage\Cepi\database\schema.sql`
3. Klik op "Open"
4. Het SQL bestand wordt geladen in een query tabblad
5. Klik op "Execute" (F9) om het schema te importeren

**Optie B: Kopieer en plak de SQL**
1. Open `database/schema.sql` in een teksteditor
2. Kopieer de volledige inhoud
3. Plak het in een nieuw query tabblad in HeidiSQL
4. Klik op "Execute" (F9)

## Stap 5: Verifieer dat alles werkt

Na het importeren zou je 3 tabellen moeten zien in de `cepi` database:

1. ✅ **organisations** - Voor organisaties
2. ✅ **members** - Voor leden
3. ✅ **import_logs** - Voor import geschiedenis

## Stap 6: Test de connectie

Ga naar: http://localhost:8000/test-db-connection.php

Dit script test of alles correct werkt.

## Problemen oplossen

**Als je een fout krijgt:**
- Controleer of de database `cepi` bestaat
- Controleer of je de juiste database hebt geselecteerd voordat je het schema importeert
- Zorg dat je MySQL/MariaDB server draait
- Controleer je gebruikersrechten (root moet CREATE en INSERT rechten hebben)



