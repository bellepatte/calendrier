-- Installation complète (nouvelle base) — tables préfixées CALENDRIERCLUB_
-- (le préfixe se change dans api.php / config.php : DB_PREFIX)
CREATE TABLE CALENDRIERCLUB_disciplines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  image VARCHAR(255) NULL COMMENT 'chemin de l''image par défaut, ex : images/route.png',
  priority INT NOT NULL DEFAULT 100 COMMENT 'plus petit = affiché en premier',
  needs_detail TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = "à préciser" (type Autre)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO CALENDRIERCLUB_disciplines (name, image, priority, needs_detail) VALUES
 ('Route','images/route.png',10,0), ('Trail','images/trail.png',20,0), ('Marche','images/marche.png',30,0),
 ('Marche Nordique','images/marche-nordique.png',40,0), ('Cross','images/cross.png',50,0), ('Piste','images/piste.png',60,0),
 ('Lancers','images/lancers.png',70,0), ('Sauts','images/sauts.png',80,0), ('Combinés','images/combines.png',90,0),
 ('Autre','images/autre.png',1000,1);

CREATE TABLE CALENDRIERCLUB_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  jour DATE NOT NULL,
  heure TIME NULL,
  place VARCHAR(50) NOT NULL,
  other VARCHAR(50) NOT NULL DEFAULT '',
  site VARCHAR(1024) NOT NULL DEFAULT '',
  image_url VARCHAR(1024) NOT NULL DEFAULT '',
  image_file VARCHAR(40) NULL,
  formats VARCHAR(120) NOT NULL,
  young VARCHAR(20) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  creator VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (jour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CALENDRIERCLUB_event_disciplines (
  event_id INT UNSIGNED NOT NULL,
  discipline_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, discipline_id),
  FOREIGN KEY (event_id) REFERENCES CALENDRIERCLUB_events(id) ON DELETE CASCADE,
  FOREIGN KEY (discipline_id) REFERENCES CALENDRIERCLUB_disciplines(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CALENDRIERCLUB_participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  epreuves VARCHAR(600) NOT NULL,
  added_by VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES CALENDRIERCLUB_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CALENDRIERCLUB_login_attempts (
  ip_key VARCHAR(60) NOT NULL PRIMARY KEY,
  fails INT UNSIGNED NOT NULL DEFAULT 0,
  last_fail INT UNSIGNED NOT NULL,
  locked_until INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
