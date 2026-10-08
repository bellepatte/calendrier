<?php
// Copiez ce fichier en config.php sur le serveur et renseignez vos vraies valeurs.
// config.php est dans .gitignore : ne le publiez jamais sur GitHub.
const DB_HOST = 'localhost';
const DB_NAME = 'nom_de_la_base';
const DB_USER = 'utilisateur';
const DB_PASS = 'mot_de_passe_base';

const GLOBAL_PASSWORD = 'changez-moi';   // mot de passe global : 12 caractères ou plus
const ADMIN_CODE      = 'changez-moi-admin';  // code du mode admin (bouton « Mode admin »)
const MAX_DESC        = 10000;
const DB_PREFIX       = 'CALENDRIERCLUB_';   // préfixe des tables (optionnel : c'est la valeur par défaut)

date_default_timezone_set('Europe/Paris');
