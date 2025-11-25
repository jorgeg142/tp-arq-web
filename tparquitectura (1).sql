-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 24-11-2025 a las 23:06:46
-- Versión del servidor: 8.4.3
-- Versión de PHP: 8.3.26

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
  DECLARE v_need             INT DEFAULT 0;
  DECLARE v_bolsa_id         BIGINT;
  DECLARE v_bolsa_saldo      INT;
  DECLARE v_to_use           INT;
  DECLARE v_cabecera_id      BIGINT;

  -- normalizar concepto: 0 => NULL
  IF p_concepto_uso_id = 0 THEN
    SET p_concepto_uso_id = NULL;
  END IF;

  -- validar puntos
  IF p_puntos_a_usar IS NULL OR p_puntos_a_usar <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cantidad de puntos a usar inválida.';
  END IF;

  -- calcular total disponible (no vencidos y saldo > 0)
  SELECT COALESCE(SUM(saldo_puntos),0)
    INTO v_total_disponible
  FROM bolsas_puntos
  WHERE cliente_id = p_cliente_id
    AND saldo_puntos > 0
    AND (fecha_caducidad IS NULL OR fecha_caducidad >= CURDATE());

  IF v_total_disponible < p_puntos_a_usar THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Puntos insuficientes.';
  END IF;

  -- crear cabecera (concepto puede ser NULL en modo libre)
  INSERT INTO uso_puntos_cab (cliente_id, puntaje_utilizado, concepto_uso_id, fecha)
  VALUES (p_cliente_id, p_puntos_a_usar, p_concepto_uso_id, NOW());
  SET v_cabecera_id = LAST_INSERT_ID();

  SET v_need = p_puntos_a_usar;

  -- consumir en FIFO usando SELECT ... FOR UPDATE (requiere transacción abierta desde Laravel)
  WHILE v_need > 0 DO
    -- limpiar variables para detectar "sin filas"
    SET v_bolsa_id := NULL;
    SET v_bolsa_saldo := NULL;

    SELECT id, saldo_puntos
      INTO v_bolsa_id, v_bolsa_saldo
    FROM bolsas_puntos
    WHERE cliente_id = p_cliente_id
      AND saldo_puntos > 0
      AND (fecha_caducidad IS NULL OR fecha_caducidad >= CURDATE())
    ORDER BY fecha_asignacion ASC
    LIMIT 1
    FOR UPDATE;

    IF v_bolsa_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay bolsas disponibles al procesar FIFO.';
    END IF;

    IF v_bolsa_saldo >= v_need THEN
      SET v_to_use = v_need;
    ELSE
      SET v_to_use = v_bolsa_saldo;
    END IF;

    -- detallar el uso
    INSERT INTO uso_puntos_det (cabecera_id, bolsa_id, puntaje_utilizado,fecha_detalle)
    VALUES (v_cabecera_id, v_bolsa_id, v_to_use, NOW());

    -- actualizar la bolsa: utilizado y saldo
    UPDATE bolsas_puntos
    SET puntaje_utilizado = puntaje_utilizado + v_to_use,
        saldo_puntos      = saldo_puntos - v_to_use
    WHERE id = v_bolsa_id;

    -- disminuir lo que falta consumir
    SET v_need = v_need - v_to_use;
  END WHILE;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_calcular_puntos` (`monto` DECIMAL(18,2)) RETURNS INT DETERMINISTIC BEGIN
  DECLARE puntos INT DEFAULT 0;
  DECLARE eq DECIMAL(18,4);
  DECLARE r_id INT;

  /* Regla activa cuyo rango contiene el monto.
     Preferimos una regla con límite superior (específica) por sobre la global (sin límite superior). */
  SELECT id, monto_equivalencia
    INTO r_id, eq
  FROM reglas_asignacion
  WHERE activo = 1
    AND limite_inferior <= monto
    AND (limite_superior IS NULL OR monto <= limite_superior)
  ORDER BY (limite_superior IS NULL) ASC,   -- específicas primero
           limite_inferior DESC             -- en caso de empate, la de mayor piso
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
  `id` bigint NOT NULL,
  `cliente_id` int NOT NULL,
  `fecha_asignacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_caducidad` date DEFAULT NULL,
  `puntaje_asignado` int NOT NULL,
  `puntaje_utilizado` int NOT NULL DEFAULT '0',
  `saldo_puntos` int NOT NULL,
  `monto_operacion` decimal(18,4) DEFAULT '0.0000',
  `origen` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `param_vencimiento_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bolsas_puntos`
--

INSERT INTO `bolsas_puntos` (`id`, `cliente_id`, `fecha_asignacion`, `fecha_caducidad`, `puntaje_asignado`, `puntaje_utilizado`, `saldo_puntos`, `monto_operacion`, `origen`, `param_vencimiento_id`) VALUES
(13, 4, '2025-11-04 11:47:00', '2025-11-04', 10, 10, 0, 200000.0000, 'xxxx', 1),
(14, 4, '2025-11-04 15:23:00', '2025-11-05', 10, 10, 0, 200000.0000, 'test', 1),
(17, 4, '2025-11-04 17:32:00', '2025-11-05', 9, 9, 0, 99999.0000, 'test regla', 1),
(19, 4, '2025-11-23 13:54:00', '2025-11-24', 199999, 0, 199999, 999999999.0000, 'test para niveles', 1),
(21, 7, '2025-11-23 00:00:00', NULL, 100, 100, 0, 0.0000, 'Bienvenida por referido', NULL),
(22, 7, '2025-11-23 00:00:00', NULL, 100, 0, 100, 0.0000, 'Bonus por referido', NULL),
(23, 9, '2025-11-23 00:00:00', NULL, 100, 0, 100, 0.0000, 'Bienvenida por referido', NULL),
(24, 9, '2025-11-23 20:54:00', '2025-11-25', 5, 0, 5, 100000.0000, 'test', 1),
(25, 5, '2025-11-24 00:00:00', NULL, 100, 0, 100, 0.0000, 'Bonus por referido', NULL),
(26, 10, '2025-11-24 00:00:00', NULL, 100, 0, 100, 0.0000, 'Bienvenida por referido', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
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
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `numero_documento` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo_documento` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nacionalidad` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  `nivel_id` bigint UNSIGNED DEFAULT NULL,
  `codigo_referido` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `referido_por_id` bigint UNSIGNED DEFAULT NULL,
  `puntos_por_referir` int DEFAULT NULL,
  `puntos_bienvenida` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `apellido`, `numero_documento`, `tipo_documento`, `nacionalidad`, `email`, `telefono`, `fecha_nacimiento`, `fecha_alta`, `activo`, `nivel_id`, `codigo_referido`, `referido_por_id`, `puntos_por_referir`, `puntos_bienvenida`) VALUES
(4, 'test2', 'test22', '987654321', 'ci', 'paraguay', 'test@gmail.com', '973407620475', '2025-11-03', '2025-11-03 10:36:07', 1, NULL, 'PPFRYDQRNI', NULL, 100, 100),
(5, 'testpost', 'testpost', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-03 11:24:38', 0, NULL, '0P7ZHKBM4X', NULL, 100, 100),
(6, 'testpost', 'testpost', NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-03 21:14:36', 0, NULL, 'ZK76OKBXWQ', NULL, 100, 100),
(7, 'test2 referiddo', 'test', '78764346', NULL, NULL, NULL, NULL, NULL, '2025-11-23 14:20:51', 1, NULL, 'MWJXNDW1TK', 1, 100, 100),
(8, 'test referido', 'vacio', '9686778', NULL, NULL, NULL, NULL, NULL, '2025-11-23 14:42:36', 1, NULL, 'G05C2O29FQ', NULL, 100, 100),
(9, 'test referido 2', 'test', '84574', NULL, NULL, NULL, NULL, NULL, '2025-11-23 17:52:42', 1, NULL, '0QCAWM4S0A', 7, 100, 100),
(10, 'test final', 'x', '34545', NULL, NULL, NULL, NULL, NULL, '2025-11-24 19:48:33', 1, NULL, 'JBG9PQDJF7', 5, 100, 100);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conceptos_uso`
--

CREATE TABLE `conceptos_uso` (
  `id` int NOT NULL,
  `descripcion_concepto` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `puntos_requeridos` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conceptos_uso`
--

INSERT INTO `conceptos_uso` (`id`, `descripcion_concepto`, `puntos_requeridos`, `activo`) VALUES
(1, 'Vale descuento 10%', 1000, 1),
(2, 'Premio: Auriculares', 2500, 1),
(4, 'test prueba', 100, 1),
(5, 'test prueba 5 puntos', 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2024_06_12_000000_create_niveles_table', 2),
(5, '2024_07_05_000001_add_referidos_to_clientes_table', 3),
(6, '2024_07_06_000002_add_referido_bonus_columns_to_clientes_table', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `niveles`
--

CREATE TABLE `niveles` (
  `id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `min_puntos` int UNSIGNED NOT NULL,
  `max_puntos` int UNSIGNED DEFAULT NULL,
  `beneficios` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `niveles`
--

INSERT INTO `niveles` (`id`, `nombre`, `slug`, `descripcion`, `min_puntos`, `max_puntos`, `beneficios`, `created_at`, `updated_at`) VALUES
(1, 'Bronce', 'bronce', 'Entrada al programa', 0, 999, 'Acumulación estándar de puntos\nAcceso a campañas generales', '2025-11-23 03:48:54', '2025-11-23 03:48:54'),
(2, 'Plata', 'plata', 'Clientes frecuentes', 1000, 4999, 'Prioridad en atención y envíos\nOfertas exclusivas mensuales', '2025-11-23 03:48:54', '2025-11-23 03:48:54'),
(3, 'Oro', 'oro', 'Clientes premium', 5000, 9999, 'Bonificación de puntos por compra recurrente\nAcceso anticipado a lanzamientos', '2025-11-23 03:48:54', '2025-11-23 03:48:54'),
(4, 'Platino', 'platino', 'Clientes VIP', 10000, 10999, 'Mayor multiplicador de puntos\r\nBeneficios y experiencias exclusivas', '2025-11-23 03:48:54', '2025-11-23 16:49:58'),
(5, 'test', 'test', 'esto es un test', 11000, 11999, 'a', '2025-11-23 04:18:46', '2025-11-23 16:50:12'),
(6, 'zdassdsd', 'zdassdsd', 'test', 13000, 14000, 'sasasd', '2025-11-25 01:45:29', '2025-11-25 01:45:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `param_vencimientos`
--

CREATE TABLE `param_vencimientos` (
  `id` int NOT NULL,
  `fecha_inicio_validez` date NOT NULL,
  `fecha_fin_validez` date DEFAULT NULL,
  `dias_duracion` int NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `param_vencimientos`
--

INSERT INTO `param_vencimientos` (`id`, `fecha_inicio_validez`, `fecha_fin_validez`, `dias_duracion`, `descripcion`, `activo`) VALUES
(1, '2025-11-01', NULL, 365, 'Vigencia general 365 diass', 1),
(4, '2025-11-03', NULL, 30, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `procesos_planificados`
--

CREATE TABLE `procesos_planificados` (
  `id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ultima_ejecucion` datetime DEFAULT NULL,
  `detalles` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reglas_asignacion`
--

CREATE TABLE `reglas_asignacion` (
  `id` int NOT NULL,
  `limite_inferior` int NOT NULL DEFAULT (0),
  `limite_superior` int DEFAULT NULL,
  `monto_equivalencia` int NOT NULL DEFAULT (0),
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` date DEFAULT NULL,
  `updated_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reglas_asignacion`
--

INSERT INTO `reglas_asignacion` (`id`, `limite_inferior`, `limite_superior`, `monto_equivalencia`, `descripcion`, `activo`, `created_at`, `updated_at`) VALUES
(3, 100000, 200000, 20000, 'test', 1, NULL, NULL),
(9, 0, 99999, 10000, 'test 2', 1, '2025-11-04', '2025-11-04'),
(10, 200001, NULL, 5000, NULL, 1, '2025-11-22', '2025-11-23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('iZubSxGhqDz8VNKPDhOA9ndw3x2tYkuUYZtHqSNI', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQXQ4bzlWeU82WWhwVnNKUlFGekFydGpzZmRVZFk4NURGdXVlSXZDViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1763937155),
('j7ExrIEmlrA7LHOek6hYjfmrGpb9SP8FUSRSkACS', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiNGhrMVJtSVA2NXV0Tzd1Q01zeVZuTE1CSENubDZ6VVlTQlBSSml5OCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9uaXZlbGVzLzEvZWRpdCI7czo1OiJyb3V0ZSI7czoxMjoibml2ZWxlcy5lZGl0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1763989794),
('r7KsNuHweurXRXUcFXevwOYWQ7ac5v1QVKtPOls9', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiQ3dJTGFLODBYVFlOc0tUdVVwMXBSU01za0NVdFhJdTZ2Nm1oSTk1YyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MTc0OiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvY2xpZW50ZXMvc2VnbWVudGFjaW9uP2NvbXByYXNfbWluPSZlZGFkX21heD0mZWRhZF9taW49JmVzdGFkbz0mbW9udG9fbWF4PSZtb250b19taW49MTAwMDQ5OTk5OCZuYWNpb25hbGlkYWQ9Jm9yZGVuPW1vbnRvX2Rlc2MmcGVyX3BhZ2U9MTAmcHVudG9zX21pbj0mcT0iO3M6NToicm91dGUiO3M6MjE6ImNsaWVudGVzLnNlZ21lbnRhY2lvbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1764024937),
('tssCUVs1gczipbFko9tn9fvwl730nlmYfBaCj6M1', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSmhHSnF6Rkc2ZlRueEoxNUpJVjRoR1loR1lJc2V5enZ0VFJmN2gxSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1764022349);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_puntos_cab`
--

CREATE TABLE `uso_puntos_cab` (
  `id` bigint NOT NULL,
  `cliente_id` int NOT NULL,
  `puntaje_utilizado` int NOT NULL,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `concepto_uso_id` int DEFAULT NULL,
  `comprobante` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` varchar(30) COLLATE utf8mb4_general_ci DEFAULT 'COMPLETADO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `uso_puntos_cab`
--

INSERT INTO `uso_puntos_cab` (`id`, `cliente_id`, `puntaje_utilizado`, `fecha`, `concepto_uso_id`, `comprobante`, `estado`) VALUES
(8, 4, 5, '2025-11-04 09:51:13', NULL, NULL, 'COMPLETADO'),
(11, 4, 5, '2025-11-04 10:35:21', NULL, NULL, 'COMPLETADO'),
(12, 4, 5, '2025-11-04 12:24:56', 5, 'test 3', 'COMPLETADO'),
(13, 7, 100, '2025-11-23 18:54:09', 4, 'x', 'COMPLETADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_puntos_det`
--

CREATE TABLE `uso_puntos_det` (
  `id` bigint NOT NULL,
  `cabecera_id` bigint NOT NULL,
  `bolsa_id` bigint NOT NULL,
  `puntaje_utilizado` int NOT NULL,
  `fecha_detalle` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `uso_puntos_det`
--

INSERT INTO `uso_puntos_det` (`id`, `cabecera_id`, `bolsa_id`, `puntaje_utilizado`, `fecha_detalle`) VALUES
(4, 8, 13, 5, '2025-11-04 09:51:13'),
(5, 11, 13, 5, '2025-11-04 10:35:21'),
(6, 12, 14, 5, '2025-11-04 12:24:56'),
(7, 13, 21, 100, '2025-11-23 18:54:09');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_saldo_puntos_cliente`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_saldo_puntos_cliente` (
`apellido` varchar(100)
,`cliente_id` int
,`nombre` varchar(100)
,`saldo_total` decimal(32,0)
,`total_asignado` decimal(32,0)
);

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
  ADD KEY `idx_email` (`email`),
  ADD KEY `clientes_nivel_id_foreign` (`nivel_id`);

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
-- Indices de la tabla `niveles`
--
ALTER TABLE `niveles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `niveles_slug_unique` (`slug`);

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
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `conceptos_uso`
--
ALTER TABLE `conceptos_uso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `niveles`
--
ALTER TABLE `niveles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `param_vencimientos`
--
ALTER TABLE `param_vencimientos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `procesos_planificados`
--
ALTER TABLE `procesos_planificados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reglas_asignacion`
--
ALTER TABLE `reglas_asignacion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `uso_puntos_cab`
--
ALTER TABLE `uso_puntos_cab`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `uso_puntos_det`
--
ALTER TABLE `uso_puntos_det`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_saldo_puntos_cliente`
--
DROP TABLE IF EXISTS `vw_saldo_puntos_cliente`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_saldo_puntos_cliente`  AS SELECT `c`.`id` AS `cliente_id`, `c`.`nombre` AS `nombre`, `c`.`apellido` AS `apellido`, coalesce(sum(`b`.`saldo_puntos`),0) AS `saldo_total`, coalesce(sum(`b`.`puntaje_asignado`),0) AS `total_asignado` FROM (`clientes` `c` left join `bolsas_puntos` `b` on(((`b`.`cliente_id` = `c`.`id`) and ((`b`.`fecha_caducidad` is null) or (`b`.`fecha_caducidad` >= curdate()))))) GROUP BY `c`.`id`, `c`.`nombre`, `c`.`apellido` ;

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
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_nivel_id_foreign` FOREIGN KEY (`nivel_id`) REFERENCES `niveles` (`id`) ON DELETE SET NULL;

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
