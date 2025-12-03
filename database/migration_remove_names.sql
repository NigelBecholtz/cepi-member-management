-- Migration: Verwijder first_name en last_name kolommen
-- Voer dit uit in HeidiSQL op de cepi database

USE cepi;

-- Verwijder first_name en last_name kolommen
ALTER TABLE members DROP COLUMN IF EXISTS first_name;
ALTER TABLE members DROP COLUMN IF EXISTS last_name;



