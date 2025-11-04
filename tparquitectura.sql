-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-11-2025 a las 18:53:50
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
-- Base de datos: `tparquitectura`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_asignar_puntos` (IN `p_cliente_id` INT, IN `p_monto_operacion` DECIMAL(18,4), IN `p_origen` VARCHAR(100))   BEGIN
  DECLARE v_puntos INT DEFAULT 0;
  DECLARE v_param_id INT DEFAULT NULL;
  DECLARE v_dias INT DEFAULT NULL;
  DECLARE v_fecha_cad DATE DEFAULT NULL;
  DECLARE v_fecha_asign DATETIME;
  START TRANSACTION;
    SET v_puntos = fn_calcular_puntos(p_monto_operacion);
    IF v_puntos <= 0 THEN
      -- No se asignan puntos; salimos
      ROLLBACK;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No corresponde asignar puntos para ese monto.';
    END IF;

    -- Tomar parámetro de vencimiento activo más reciente (por fecha_inicio_validez)
    SELECT id, dias_duracion INTO v_param_id, v_dias
    FROM param_vencimientos
    WHERE activo = 1
      AND (fecha_inicio_validez <= CURDATE())
      AND (fecha_fin_validez IS NULL OR fecha_fin_validez >= CURDATE())
    ORDER BY fecha_inicio_validez DESC
    LIMIT 1;

    SET v_fecha_asign = NOW();
    IF v_param_id IS NOT NULL THEN
      IF v_dias IS NOT NULL THEN
        SET v_fecha_cad = DATE_ADD(DATE(v_fecha_asign), INTERVAL v_dias DAY);
      ELSE
        SET v_fecha_cad = NULL;
      END IF;
    END IF;

    INSERT INTO bolsas_puntos (
      cliente_id, fecha_asignacion, fecha_caducidad,
      puntaje_asignado, puntaje_utilizado, saldo_puntos,
      monto_operacion, origen, param_vencimiento_id
    ) VALUES (
      p_cliente_id, v_fecha_asign, v_fecha_cad,
      v_puntos, 0, v_puntos,
      p_monto_operacion, p_origen, v_param_id
    );

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_procesar_vencimientos` ()   BEGIN
  START TRANSACTION;
    -- Selecciona bolsas vencidas con saldo > 0
    UPDATE bolsas_puntos
    SET puntaje_utilizado = puntaje_utilizado + saldo_puntos,
        saldo_puntos = 0
    WHERE fecha_caducidad IS NOT NULL
      AND fecha_caducidad < CURDATE()
      AND saldo_puntos > 0;
  COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_usar_puntos_fifo` (IN `p_cliente_id` INT, IN `p_puntos_a_usar` INT, IN `p_concepto_uso_id` INT)   BEGIN
  DECLARE v_total_disponible INT DEFAULT 0;
  DECLARE v_need INT DEFAULT 0;
  DECLARE v_bolsa_id BIGINT;
  DECLARE v_bolsa_saldo INT;
  DECLARE v_to_use INT;
  DECLARE v_cabecera_id BIGINT;

  START TRANSACTION;
    -- calcular total disponible (no vencidos y saldo > 0)
    SELECT COALESCE(SUM(saldo_puntos),0) INTO v_total_disponible
    FROM bolsas_puntos
    WHERE cliente_id = p_cliente_id
      AND saldo_puntos > 0
      AND (fecha_caducidad IS NULL OR fecha_caducidad >= CURDATE());

    IF v_total_disponible < p_puntos_a_usar THEN
      ROLLBACK;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Puntos insuficientes.';
    END IF;

    -- crear cabecera
    INSERT INTO uso_puntos_cab (cliente_id, puntaje_utilizado, concepto_uso_id)
    VALUES (p_cliente_id, p_puntos_a_usar, p_concepto_uso_id);
    SET v_cabecera_id = LAST_INSERT_ID();
    SET v_need = p_puntos_a_usar;

    -- Cursor-like loop: seleccionar bolsas FIFO (más antiguas primero) con saldo>0
    -- Usamos un bloqueo FOR UPDATE para evitar race conditions
    WHILE v_need > 0 DO
      SELECT id, saldo_puntos INTO v_bolsa_id, v_bolsa_saldo
      FROM bolsas_puntos
      WHERE cliente_id = p_cliente_id
        AND saldo_puntos > 0
        AND (fecha_caducidad IS NULL OR fecha_caducidad >= CURDATE())
      ORDER BY fecha_asignacion ASC
      LIMIT 1
      FOR UPDATE;

      IF v_bolsa_id IS NULL THEN
        -- debería haber sido detectado antes; pero por seguridad:
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error: no hay bolsas disponibles al procesar FIFO.';
      END IF;

      IF v_bolsa_saldo >= v_need THEN
        SET v_to_use = v_need;
      ELSE
        SET v_to_use = v_bolsa_saldo;
      END IF;

      -- insertar detalle y actualizar bolsa
      INSERT INTO uso_puntos_det (cabecera_id, bolsa_id, puntaje_utilizado)
      VALUES (v_cabecera_id, v_bolsa_id, v_to_use);

      UPDATE bolsas_puntos
      SET puntaje_utilizado = puntaje_utilizado + v_to_use,
          saldo_puntos = saldo_puntos - v_to_use
      WHERE id = v_bolsa_id;

      SET v_need = v_need - v_to_use;
    END WHILE;

    COMMIT;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_calcular_puntos` (`monto` DECIMAL(18,4)) RETURNS INT(11) DETERMINISTIC BEGIN
  DECLARE puntos INT DEFAULT 0;
  DECLARE eq DECIMAL(18,4);
  DECLARE r_id INT;
  -- Tomamos la regla activa con mayor prioridad que coincida
  SELECT id, monto_equivalencia INTO r_id, eq
  FROM reglas_asignacion
  WHERE activo = 1
    AND limite_inferior <= monto
    AND (limite_superior IS NULL OR monto <= limite_superior)
  ORDER BY prioridad ASC, limite_inferior DESC
  LIMIT 1;
  IF r_id IS NOT NULL THEN
    SET puntos = FLOOR(monto / eq);
  ELSE
    SET puntos = 0;
  END IF;
  RETURN puntos;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bolsas_puntos`
--

CREATE TABLE `bolsas_puntos` (
  `id` bigint(20) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_caducidad` date DEFAULT NULL,
  `puntaje_asignado` int(11) NOT NULL,
  `puntaje_utilizado` int(11) NOT NULL DEFAULT 0,
  `saldo_puntos` int(11) NOT NULL,
  `monto_operacion` decimal(18,4) DEFAULT 0.0000,
  `origen` varchar(100) DEFAULT NULL,
  `param_vencimiento_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bolsas_puntos`
--

INSERT INTO `bolsas_puntos` (`id`, `cliente_id`, `fecha_asignacion`, `fecha_caducidad`, `puntaje_asignado`, `puntaje_utilizado`, `saldo_puntos`, `monto_operacion`, `origen`, `param_vencimiento_id`) VALUES
(1, 1, '2025-11-01 17:20:05', '2026-11-01', 24, 0, 24, 120.0000, 'CargaInicial', 1),
(2, 2, '2025-11-02 03:03:00', '2025-11-30', 50000, 2500, 47500, 0.0000, NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-boost.roster.scan', 'a:2:{s:6:\"roster\";O:21:\"Laravel\\Roster\\Roster\":3:{s:13:\"\0*\0approaches\";O:29:\"Illuminate\\Support\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:11:\"\0*\0packages\";O:32:\"Laravel\\Roster\\PackageCollection\":2:{s:8:\"\0*\0items\";a:8:{i:0;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^12.0\";s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:LARAVEL\";s:14:\"\0*\0packageName\";s:17:\"laravel/framework\";s:10:\"\0*\0version\";s:7:\"12.36.1\";s:6:\"\0*\0dev\";b:0;}i:1;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:6:\"v0.3.7\";s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:PROMPTS\";s:14:\"\0*\0packageName\";s:15:\"laravel/prompts\";s:10:\"\0*\0version\";s:5:\"0.3.7\";s:6:\"\0*\0dev\";b:0;}i:2;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:6:\"v0.3.2\";s:10:\"\0*\0package\";E:33:\"Laravel\\Roster\\Enums\\Packages:MCP\";s:14:\"\0*\0packageName\";s:11:\"laravel/mcp\";s:10:\"\0*\0version\";s:5:\"0.3.2\";s:6:\"\0*\0dev\";b:1;}i:3;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^1.24\";s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:PINT\";s:14:\"\0*\0packageName\";s:12:\"laravel/pint\";s:10:\"\0*\0version\";s:6:\"1.25.1\";s:6:\"\0*\0dev\";b:1;}i:4;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^1.41\";s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:SAIL\";s:14:\"\0*\0packageName\";s:12:\"laravel/sail\";s:10:\"\0*\0version\";s:6:\"1.47.0\";s:6:\"\0*\0dev\";b:1;}i:5;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:4:\"^4.1\";s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:PEST\";s:14:\"\0*\0packageName\";s:12:\"pestphp/pest\";s:10:\"\0*\0version\";s:5:\"4.1.3\";s:6:\"\0*\0dev\";b:1;}i:6;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:6:\"12.4.1\";s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:PHPUNIT\";s:14:\"\0*\0packageName\";s:15:\"phpunit/phpunit\";s:10:\"\0*\0version\";s:6:\"12.4.1\";s:6:\"\0*\0dev\";b:1;}i:7;O:22:\"Laravel\\Roster\\Package\":6:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:0:\"\";s:10:\"\0*\0package\";E:41:\"Laravel\\Roster\\Enums\\Packages:TAILWINDCSS\";s:14:\"\0*\0packageName\";s:11:\"tailwindcss\";s:10:\"\0*\0version\";s:6:\"4.1.16\";s:6:\"\0*\0dev\";b:1;}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:21:\"\0*\0nodePackageManager\";E:43:\"Laravel\\Roster\\Enums\\NodePackageManager:NPM\";}s:9:\"timestamp\";i:1762050668;}', 1762137068);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `numero_documento` varchar(50) DEFAULT NULL,
  `tipo_documento` varchar(30) DEFAULT NULL,
  `nacionalidad` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_alta` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `apellido`, `numero_documento`, `tipo_documento`, `nacionalidad`, `email`, `telefono`, `fecha_nacimiento`, `fecha_alta`, `activo`) VALUES
(1, 'Juan', 'Pérez', '12345678', 'CI', NULL, 'juan.perez@example.com', NULL, NULL, '2025-11-01 17:20:05', 1),
(2, 'test', 'test', '645656', 'CI', 'asdsad', 'admin@ecodrive.zm', '0985602385', '2025-11-04', '2025-11-01 21:48:36', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conceptos_uso`
--

CREATE TABLE `conceptos_uso` (
  `id` int(11) NOT NULL,
  `descripcion_concepto` varchar(200) NOT NULL,
  `puntos_requeridos` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conceptos_uso`
--

INSERT INTO `conceptos_uso` (`id`, `descripcion_concepto`, `puntos_requeridos`, `activo`) VALUES
(1, 'Vale descuento 10%', 1000, 1),
(2, 'Premio: Auriculares', 2500, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `param_vencimientos`
--

CREATE TABLE `param_vencimientos` (
  `id` int(11) NOT NULL,
  `fecha_inicio_validez` date NOT NULL,
  `fecha_fin_validez` date DEFAULT NULL,
  `dias_duracion` int(11) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `param_vencimientos`
--

INSERT INTO `param_vencimientos` (`id`, `fecha_inicio_validez`, `fecha_fin_validez`, `dias_duracion`, `descripcion`, `activo`) VALUES
(1, '2025-11-01', NULL, 365, 'Vigencia general 365 diass', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procesos_planificados`
--

CREATE TABLE `procesos_planificados` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `ultima_ejecucion` datetime DEFAULT NULL,
  `detalles` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reglas_asignacion`
--

CREATE TABLE `reglas_asignacion` (
  `id` int(11) NOT NULL,
  `limite_inferior` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `limite_superior` decimal(18,4) DEFAULT NULL,
  `monto_equivalencia` decimal(18,4) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `prioridad` int(11) DEFAULT 10,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reglas_asignacion`
--

INSERT INTO `reglas_asignacion` (`id`, `limite_inferior`, `limite_superior`, `monto_equivalencia`, `descripcion`, `prioridad`, `activo`) VALUES
(2, 100.0000, 150.0000, 1.0000, '1 punto cada 5 de compra (>=100)', 3, 1),
(3, 150.0000, 200.0000, 2.0000, 'test', 1, 1),
(5, 600.0000, 650.0000, 12.0000, 'test', 4, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('1mwSsRk0fOA39rLBV72o5NDd5o9keozuCPzHSALF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicm5MR1pLMGNRQ2huRnZoZlYwNFdWTHdTem5sc29LenlJQ1hsMDFodCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDQ6Imh0dHA6Ly9hNTZjNmUyN2ZhZWYubmdyb2stZnJlZS5hcHAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjk6ImRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1762105866),
('2CyykH8XpWGq3Xx8vNzn1mFmhKGNq7ihvdBDAVQ1', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiUW1Kd1dqU2xPS1FOZnBOOEFxTldoZkt1cm54Z09lYU55M2RDcHRRWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC91c29zIjtzOjU6InJvdXRlIjtzOjEwOiJ1c29zLmluZGV4Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1762113856),
('WaWDTOcLUlNRto5iotdTytsakXcZbmxlfyKay3po', NULL, '127.0.0.1', 'PostmanRuntime/7.42.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSTdOU1VCRmtFanZXMzR5TGVCd3ZDbDJtN212NHpXZmxyTWRIbm4zdiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1762114102);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_puntos_cab`
--

CREATE TABLE `uso_puntos_cab` (
  `id` bigint(20) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `puntaje_utilizado` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `concepto_uso_id` int(11) NOT NULL,
  `comprobante` varchar(200) DEFAULT NULL,
  `estado` varchar(30) DEFAULT 'COMPLETADO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `uso_puntos_cab`
--

INSERT INTO `uso_puntos_cab` (`id`, `cliente_id`, `puntaje_utilizado`, `fecha`, `concepto_uso_id`, `comprobante`, `estado`) VALUES
(1, 2, 2500, '2025-11-02 00:04:02', 2, 'test', 'PENDIENTE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_puntos_det`
--

CREATE TABLE `uso_puntos_det` (
  `id` bigint(20) NOT NULL,
  `cabecera_id` bigint(20) NOT NULL,
  `bolsa_id` bigint(20) NOT NULL,
  `puntaje_utilizado` int(11) NOT NULL,
  `fecha_detalle` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `uso_puntos_det`
--

INSERT INTO `uso_puntos_det` (`id`, `cabecera_id`, `bolsa_id`, `puntaje_utilizado`, `fecha_detalle`) VALUES
(1, 1, 2, 2500, '2025-11-02 00:04:02');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_saldo_puntos_cliente`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_saldo_puntos_cliente` (
`cliente_id` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`saldo_total` decimal(32,0)
,`total_asignado` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_saldo_puntos_cliente`
--
DROP TABLE IF EXISTS `vw_saldo_puntos_cliente`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_saldo_puntos_cliente`  AS SELECT `c`.`id` AS `cliente_id`, `c`.`nombre` AS `nombre`, `c`.`apellido` AS `apellido`, coalesce(sum(`b`.`saldo_puntos`),0) AS `saldo_total`, coalesce(sum(`b`.`puntaje_asignado`),0) AS `total_asignado` FROM (`clientes` `c` left join `bolsas_puntos` `b` on(`b`.`cliente_id` = `c`.`id` and (`b`.`fecha_caducidad` is null or `b`.`fecha_caducidad` >= curdate()))) GROUP BY `c`.`id`, `c`.`nombre`, `c`.`apellido` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `bolsas_puntos`
--
ALTER TABLE `bolsas_puntos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente_fecha` (`cliente_id`,`fecha_asignacion`),
  ADD KEY `param_vencimiento_id` (`param_vencimiento_id`);

--
-- Indices de la tabla `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`),
  ADD KEY `idx_nombre_apellido` (`apellido`,`nombre`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `conceptos_uso`
--
ALTER TABLE `conceptos_uso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `descripcion_concepto` (`descripcion_concepto`);

--
-- Indices de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indices de la tabla `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indices de la tabla `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `param_vencimientos`
--
ALTER TABLE `param_vencimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vigencia` (`fecha_inicio_validez`,`fecha_fin_validez`);

--
-- Indices de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indices de la tabla `procesos_planificados`
--
ALTER TABLE `procesos_planificados`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `reglas_asignacion`
--
ALTER TABLE `reglas_asignacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_limites` (`limite_inferior`,`limite_superior`);

--
-- Indices de la tabla `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indices de la tabla `uso_puntos_cab`
--
ALTER TABLE `uso_puntos_cab`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `concepto_uso_id` (`concepto_uso_id`);

--
-- Indices de la tabla `uso_puntos_det`
--
ALTER TABLE `uso_puntos_det`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cabecera_id` (`cabecera_id`),
  ADD KEY `idx_bolsa` (`bolsa_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `bolsas_puntos`
--
ALTER TABLE `bolsas_puntos`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `conceptos_uso`
--
ALTER TABLE `conceptos_uso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `param_vencimientos`
--
ALTER TABLE `param_vencimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `procesos_planificados`
--
ALTER TABLE `procesos_planificados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reglas_asignacion`
--
ALTER TABLE `reglas_asignacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `uso_puntos_cab`
--
ALTER TABLE `uso_puntos_cab`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `uso_puntos_det`
--
ALTER TABLE `uso_puntos_det`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bolsas_puntos`
--
ALTER TABLE `bolsas_puntos`
  ADD CONSTRAINT `bolsas_puntos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bolsas_puntos_ibfk_2` FOREIGN KEY (`param_vencimiento_id`) REFERENCES `param_vencimientos` (`id`);

--
-- Filtros para la tabla `uso_puntos_cab`
--
ALTER TABLE `uso_puntos_cab`
  ADD CONSTRAINT `uso_puntos_cab_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uso_puntos_cab_ibfk_2` FOREIGN KEY (`concepto_uso_id`) REFERENCES `conceptos_uso` (`id`);

--
-- Filtros para la tabla `uso_puntos_det`
--
ALTER TABLE `uso_puntos_det`
  ADD CONSTRAINT `uso_puntos_det_ibfk_1` FOREIGN KEY (`cabecera_id`) REFERENCES `uso_puntos_cab` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `uso_puntos_det_ibfk_2` FOREIGN KEY (`bolsa_id`) REFERENCES `bolsas_puntos` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`root`@`localhost` EVENT `ev_vencimientos_diario` ON SCHEDULE EVERY 1 DAY STARTS '2025-11-02 00:05:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_procesar_vencimientos()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
