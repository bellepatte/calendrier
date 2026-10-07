-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : bellaydb1.mysql.db
-- Généré le : mer. 07 oct. 2026 à 16:50
-- Version du serveur : 8.4.11-11
-- Version de PHP : 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `bellaydb1`
--

-- --------------------------------------------------------

--
-- Structure de la table `disciplines`
--

CREATE TABLE `disciplines` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '100',
  `needs_detail` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `disciplines`
--

INSERT INTO `disciplines` (`id`, `name`, `image`, `priority`, `needs_detail`) VALUES
(1, 'Route', 'images/route.png', 10, 0),
(2, 'Trail', 'images/trail.png', 20, 0),
(3, 'Marche', 'images/marche.png', 30, 0),
(4, 'Marche Nordique', 'images/marche-nordique.png', 40, 0),
(5, 'Cross', 'images/cross.png', 50, 0),
(6, 'Piste', 'images/piste.png', 60, 0),
(7, 'Lancers', 'images/lancers.png', 70, 0),
(8, 'Sauts', 'images/sauts.png', 80, 0),
(9, 'Combinés', 'images/combines.png', 90, 0),
(10, 'Autre', 'images/autre.png', 1000, 1),
(31, 'Marche Athlétique', 'images/piste.png', 45, 0);

-- --------------------------------------------------------

--
-- Structure de la table `events`
--

CREATE TABLE `events` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `jour` date NOT NULL,
  `heure` time DEFAULT NULL,
  `place` varchar(50) NOT NULL,
  `other` varchar(50) NOT NULL DEFAULT '',
  `site` varchar(1024) NOT NULL DEFAULT '',
  `image_url` varchar(1024) NOT NULL DEFAULT '',
  `image_file` varchar(40) DEFAULT NULL,
  `formats` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `young` varchar(20) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `creator` varchar(120) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `event_disciplines`
--

CREATE TABLE `event_disciplines` (
  `event_id` int NOT NULL,
  `discipline_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fails` int UNSIGNED NOT NULL DEFAULT '0',
  `last_fail` int UNSIGNED NOT NULL,
  `locked_until` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `participants`
--

CREATE TABLE `participants` (
  `id` int NOT NULL,
  `event_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `epreuves` json NOT NULL,
  `added_by` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `disciplines`
--
ALTER TABLE `disciplines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `date` (`jour`);

--
-- Index pour la table `event_disciplines`
--
ALTER TABLE `event_disciplines`
  ADD PRIMARY KEY (`event_id`,`discipline_id`),
  ADD KEY `discipline_id` (`discipline_id`);

--
-- Index pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`ip_key`);

--
-- Index pour la table `participants`
--
ALTER TABLE `participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `disciplines`
--
ALTER TABLE `disciplines`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pour la table `events`
--
ALTER TABLE `events`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `participants`
--
ALTER TABLE `participants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `event_disciplines`
--
ALTER TABLE `event_disciplines`
  ADD CONSTRAINT `event_disciplines_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_disciplines_ibfk_2` FOREIGN KEY (`discipline_id`) REFERENCES `disciplines` (`id`) ON DELETE RESTRICT;

--
-- Contraintes pour la table `participants`
--
ALTER TABLE `participants`
  ADD CONSTRAINT `participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
