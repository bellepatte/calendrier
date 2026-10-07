<?php
// ====== À MODIFIER avant mise en ligne ======
const DB_HOST = 'localhost';          // OVH : ex. monlogin.mysql.db
const DB_NAME = 'nom_de_la_base';
const DB_USER = 'utilisateur';
const DB_PASS = 'mot_de_passe_base';

const GLOBAL_PASSWORD = 'changez-moi';   // mot de passe global (consultation)
const ADMIN_CODE      = 'changez-moi';      // code à saisir à la place de "Prénom Nom"
const MAX_DESC        = 10000;           // longueur max de la description

date_default_timezone_set('Europe/Paris');
