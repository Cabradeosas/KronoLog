-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-04-2026 a las 15:37:46
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `kronoLog_db`
--
CREATE DATABASE IF NOT EXISTS `kronoLog_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `kronoLog_db`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client`
--

CREATE TABLE `client` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `cif` varchar(10) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_service`
--

CREATE TABLE `client_service` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `cost_per_month` decimal(10,2) NOT NULL,
  `cost_per_hour` decimal(10,2) NOT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `client_service`
--
DELIMITER $$
CREATE TRIGGER `after_client_service_insert` AFTER INSERT ON `client_service` FOR EACH ROW BEGIN
    INSERT INTO client_service_history (
        client_service_id,
        cost_per_month,
        cost_per_hour,
        start_date,
        end_date
    )
    VALUES (
        NEW.id,
        NEW.cost_per_month,
        NEW.cost_per_hour,
        CURDATE(),
        NULL
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_client_service_update` AFTER UPDATE ON `client_service` FOR EACH ROW BEGIN
    IF OLD.cost_per_month != NEW.cost_per_month OR OLD.cost_per_hour != NEW.cost_per_hour THEN
        -- Cerrar el registro anterior
        UPDATE client_service_history 
        SET end_date = CURDATE() - INTERVAL 1 DAY
        WHERE client_service_id = NEW.id AND end_date IS NULL;
        
        -- Crear nuevo registro
        INSERT INTO client_service_history (client_service_id, cost_per_month, cost_per_hour, start_date)
        VALUES (NEW.id, NEW.cost_per_month, NEW.cost_per_hour, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_service_history`
--

CREATE TABLE `client_service_history` (
  `id` int(11) NOT NULL,
  `client_service_id` int(11) NOT NULL,
  `cost_per_month` decimal(10,2) NOT NULL,
  `cost_per_hour` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_status_history`
--

CREATE TABLE `client_status_history` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `service`
--

CREATE TABLE `service` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `time_worked`
--

CREATE TABLE `time_worked` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_service_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `minutes` int(11) NOT NULL,
  `message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `role` enum('admin','moderator','user') NOT NULL DEFAULT 'user',
  `hash` varchar(255) NOT NULL,
  `status` enum('active','absent','vacation') NOT NULL DEFAULT 'active',
  `mensual_cost` decimal(10,2) NOT NULL,
  `weekly_hours` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `user`
--
DELIMITER $$
CREATE TRIGGER `after_user_insert` AFTER INSERT ON `user` FOR EACH ROW BEGIN
    INSERT INTO user_cost_history (
        user_id,
        mensual_cost,
        start_date,
        end_date
    )
    VALUES (
        NEW.id,
        NEW.mensual_cost,
        CURDATE(),
        NULL
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_user_update` AFTER UPDATE ON `user` FOR EACH ROW BEGIN
    IF OLD.mensual_cost != NEW.mensual_cost THEN
        -- Cerrar el registro anterior
        UPDATE user_cost_history 
        SET end_date = CURDATE() - INTERVAL 1 DAY
        WHERE user_id = NEW.id AND end_date IS NULL;
        
        -- Crear nuevo registro
        INSERT INTO user_cost_history (user_id, mensual_cost, start_date)
        VALUES (NEW.id, NEW.mensual_cost, CURDATE());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_cost_history`
--

CREATE TABLE `user_cost_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mensual_cost` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_status`
--

CREATE TABLE `user_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `motive` enum('vacation','absent') NOT NULL DEFAULT 'vacation',
  `start_date` datetime NOT NULL DEFAULT current_timestamp(),
  `end_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cif` (`cif`);

--
-- Indices de la tabla `client_service`
--
ALTER TABLE `client_service`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_client_id_with_client` (`client_id`),
  ADD KEY `FK_service_id_with_service` (`service_id`);

--
-- Indices de la tabla `client_service_history`
--
ALTER TABLE `client_service_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_client_service_id_with_history` (`client_service_id`);

--
-- Indices de la tabla `client_status_history`
--
ALTER TABLE `client_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_client_status_history` (`client_id`);

--
-- Indices de la tabla `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `time_worked`
--
ALTER TABLE `time_worked`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_user_id_with_time_worked` (`user_id`),
  ADD KEY `FK_client_service_id_with_time_worked` (`client_service_id`);

--
-- Indices de la tabla `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `user_cost_history`
--
ALTER TABLE `user_cost_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_user_id_with_history` (`user_id`);

--
-- Indices de la tabla `user_status`
--
ALTER TABLE `user_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_user_id_with_user_status` (`user_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `client`
--
ALTER TABLE `client`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `client_service`
--
ALTER TABLE `client_service`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `client_service_history`
--
ALTER TABLE `client_service_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `client_status_history`
--
ALTER TABLE `client_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `service`
--
ALTER TABLE `service`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `time_worked`
--
ALTER TABLE `time_worked`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_cost_history`
--
ALTER TABLE `user_cost_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `user_status`
--
ALTER TABLE `user_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `client_service`
--
ALTER TABLE `client_service`
  ADD CONSTRAINT `FK_client_id_with_client` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_service_id_with_service` FOREIGN KEY (`service_id`) REFERENCES `service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `client_service_history`
--
ALTER TABLE `client_service_history`
  ADD CONSTRAINT `FK_client_service_id_with_history` FOREIGN KEY (`client_service_id`) REFERENCES `client_service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `client_status_history`
--
ALTER TABLE `client_status_history`
  ADD CONSTRAINT `FK_client_status_history` FOREIGN KEY (`client_id`) REFERENCES `client` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `time_worked`
--
ALTER TABLE `time_worked`
  ADD CONSTRAINT `FK_client_service_id_with_time_worked` FOREIGN KEY (`client_service_id`) REFERENCES `client_service` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_user_id_with_time_worked` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `user_cost_history`
--
ALTER TABLE `user_cost_history`
  ADD CONSTRAINT `FK_user_id_with_history` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `user_status`
--
ALTER TABLE `user_status`
  ADD CONSTRAINT `FK_user_id_with_user_status` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
