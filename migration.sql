-- Migration d'une base EXISTANTE (faites une sauvegarde avant !)
CREATE TABLE disciplines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  image VARCHAR(255) NULL,
  priority INT NOT NULL DEFAULT 100,
  needs_detail TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO disciplines (name, image, priority, needs_detail) VALUES
 ('Route','images/route.png',10,0), ('Trail','images/trail.png',20,0), ('Marche','images/marche.png',30,0),
 ('Marche Nordique','images/marche-nordique.png',40,0), ('Cross','images/cross.png',50,0), ('Piste','images/piste.png',60,0),
 ('Lancers','images/lancers.png',70,0), ('Sauts','images/sauts.png',80,0), ('Combinés','images/combines.png',90,0),
 ('Autre','images/autre.png',1000,1);

CREATE TABLE event_disciplines (
  event_id INT UNSIGNED NOT NULL,
  discipline_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, discipline_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO event_disciplines (event_id, discipline_id)
  SELECT e.id, d.id FROM events e JOIN disciplines d ON FIND_IN_SET(d.name, e.disciplines) > 0;

ALTER TABLE events DROP COLUMN disciplines;
