/*
 Navicat Premium Data Transfer

 Source Server         : LOCAL
 Source Server Type    : MySQL
 Source Server Version : 100432 (10.4.32-MariaDB)
 Source Host           : localhost:3306
 Source Schema         : tire_management_system

 Target Server Type    : MySQL
 Target Server Version : 100432 (10.4.32-MariaDB)
 File Encoding         : 65001

 Date: 28/07/2025 22:53:24
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for alertas
-- ----------------------------
DROP TABLE IF EXISTS `alertas`;
CREATE TABLE `alertas`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `instalacion_id` int NOT NULL,
  `tipo_alerta` enum('rotacion_30','desgaste_limite','mantenimiento') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `fecha_alerta` date NOT NULL,
  `estado` enum('pendiente','revisada','resuelta') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pendiente',
  `prioridad` enum('baja','media','alta','critica') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'media',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `instalacion_id`(`instalacion_id` ASC) USING BTREE,
  INDEX `idx_alertas_estado`(`estado` ASC) USING BTREE,
  CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`instalacion_id`) REFERENCES `instalaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 30 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for configuracion_sistema
-- ----------------------------
DROP TABLE IF EXISTS `configuracion_sistema`;
CREATE TABLE `configuracion_sistema`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `clave`(`clave` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for desechos
-- ----------------------------
DROP TABLE IF EXISTS `desechos`;
CREATE TABLE `desechos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `neumatico_id` int NOT NULL,
  `fecha_desecho` date NOT NULL,
  `horometro_final` int NULL DEFAULT NULL,
  `cocada_final` decimal(5, 2) NULL DEFAULT NULL,
  `porcentaje_desgaste_final` decimal(5, 2) NULL DEFAULT NULL,
  `horas_totales_trabajadas` int NULL DEFAULT NULL,
  `costo_hora` decimal(8, 4) NULL DEFAULT NULL,
  `valor_remanente` decimal(10, 2) NULL DEFAULT NULL,
  `motivo_desecho` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `mes_desecho` int NULL DEFAULT NULL,
  `ano_desecho` int NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `neumatico_id`(`neumatico_id` ASC) USING BTREE,
  CONSTRAINT `desechos_ibfk_1` FOREIGN KEY (`neumatico_id`) REFERENCES `neumaticos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for disenos
-- ----------------------------
DROP TABLE IF EXISTS `disenos`;
CREATE TABLE `disenos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for equipos
-- ----------------------------
DROP TABLE IF EXISTS `equipos`;
CREATE TABLE `equipos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `modelo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `horas_mes_promedio` int NULL DEFAULT 500,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `codigo`(`codigo` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 22 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for instalaciones
-- ----------------------------
DROP TABLE IF EXISTS `instalaciones`;
CREATE TABLE `instalaciones`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `neumatico_id` int NOT NULL,
  `equipo_id` int NOT NULL,
  `posicion` tinyint NOT NULL,
  `fecha_instalacion` date NOT NULL,
  `horometro_instalacion` int NULL DEFAULT NULL,
  `cocada_inicial` decimal(5, 2) NULL DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `neumatico_id`(`neumatico_id` ASC) USING BTREE,
  INDEX `equipo_id`(`equipo_id` ASC) USING BTREE,
  INDEX `idx_instalaciones_activo`(`activo` ASC) USING BTREE,
  CONSTRAINT `instalaciones_ibfk_1` FOREIGN KEY (`neumatico_id`) REFERENCES `neumaticos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `instalaciones_ibfk_2` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for marcas
-- ----------------------------
DROP TABLE IF EXISTS `marcas`;
CREATE TABLE `marcas`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for medidas
-- ----------------------------
DROP TABLE IF EXISTS `medidas`;
CREATE TABLE `medidas`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `medida` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for movimientos
-- ----------------------------
DROP TABLE IF EXISTS `movimientos`;
CREATE TABLE `movimientos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `neumatico_id` int NOT NULL,
  `equipo_origen_id` int NULL DEFAULT NULL,
  `posicion_origen` tinyint NULL DEFAULT NULL,
  `equipo_destino_id` int NULL DEFAULT NULL,
  `posicion_destino` tinyint NULL DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `horometro_movimiento` int NULL DEFAULT NULL,
  `tipo_movimiento` enum('instalacion','rotacion','retiro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `cocada_movimiento` decimal(5, 2) NULL DEFAULT NULL,
  `horas_acumuladas` int NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `neumatico_id`(`neumatico_id` ASC) USING BTREE,
  INDEX `equipo_origen_id`(`equipo_origen_id` ASC) USING BTREE,
  INDEX `equipo_destino_id`(`equipo_destino_id` ASC) USING BTREE,
  INDEX `idx_movimientos_fecha`(`fecha_movimiento` ASC) USING BTREE,
  CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`neumatico_id`) REFERENCES `neumaticos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`equipo_origen_id`) REFERENCES `equipos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`equipo_destino_id`) REFERENCES `equipos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for neumaticos
-- ----------------------------
DROP TABLE IF EXISTS `neumaticos`;
CREATE TABLE `neumaticos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_interno` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_serie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `dot` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `marca_id` int NULL DEFAULT NULL,
  `diseno_id` int NULL DEFAULT NULL,
  `medida_id` int NULL DEFAULT NULL,
  `nuevo_usado` enum('N','U','R','RXT') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'N',
  `remanente_nuevo` decimal(5, 2) NULL DEFAULT 100.00,
  `garantia_horas` int NULL DEFAULT 5000,
  `vida_util_horas` int NULL DEFAULT 5000,
  `costo_nuevo` decimal(10, 2) NULL DEFAULT NULL,
  `estado` enum('inventario','instalado','desechado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'inventario',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `codigo_interno`(`codigo_interno` ASC) USING BTREE,
  INDEX `marca_id`(`marca_id` ASC) USING BTREE,
  INDEX `diseno_id`(`diseno_id` ASC) USING BTREE,
  INDEX `medida_id`(`medida_id` ASC) USING BTREE,
  INDEX `idx_neumaticos_codigo`(`codigo_interno` ASC) USING BTREE,
  CONSTRAINT `neumaticos_ibfk_1` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `neumaticos_ibfk_2` FOREIGN KEY (`diseno_id`) REFERENCES `disenos` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `neumaticos_ibfk_3` FOREIGN KEY (`medida_id`) REFERENCES `medidas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 55 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for seguimiento_semanal
-- ----------------------------
DROP TABLE IF EXISTS `seguimiento_semanal`;
CREATE TABLE `seguimiento_semanal`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `instalacion_id` int NOT NULL,
  `fecha_medicion` date NOT NULL,
  `semana` int NOT NULL,
  `ano` int NOT NULL,
  `cocada_actual` decimal(5, 2) NULL DEFAULT NULL,
  `desgaste_semanal` decimal(5, 2) NULL DEFAULT NULL,
  `horas_trabajadas` int NULL DEFAULT NULL,
  `porcentaje_desgaste` decimal(5, 2) NULL DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `instalacion_id`(`instalacion_id` ASC) USING BTREE,
  INDEX `idx_seguimiento_fecha`(`fecha_medicion` ASC) USING BTREE,
  CONSTRAINT `seguimiento_semanal_ibfk_1` FOREIGN KEY (`instalacion_id`) REFERENCES `instalaciones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for usuarios
-- ----------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('admin','supervisor','operador') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'operador',
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `email`(`email` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Procedure structure for sp_desechar_neumatico
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_desechar_neumatico`;
delimiter ;;
CREATE PROCEDURE `sp_desechar_neumatico`(IN p_neumatico_id INT,
    IN p_fecha_desecho DATE,
    IN p_horometro_final INT,
    IN p_cocada_final DECIMAL(5,2),
    IN p_motivo_desecho VARCHAR(200))
BEGIN
    DECLARE v_horas_totales INT DEFAULT 0;
    DECLARE v_costo_nuevo DECIMAL(10,2);
    DECLARE v_porcentaje_final DECIMAL(5,2);
    DECLARE v_costo_hora DECIMAL(8,4);
    DECLARE v_valor_remanente DECIMAL(10,2);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Obtener datos del neumático
    SELECT costo_nuevo INTO v_costo_nuevo
    FROM neumaticos WHERE id = p_neumatico_id;
    
    -- Calcular horas totales trabajadas
    SELECT COALESCE(SUM(horas_trabajadas), 0) INTO v_horas_totales
    FROM seguimiento_semanal ss
    JOIN instalaciones i ON ss.instalacion_id = i.id
    WHERE i.neumatico_id = p_neumatico_id;
    
    -- Calcular porcentaje de desgaste final
    SET v_porcentaje_final = ((100 - p_cocada_final) / 100) * 100;
    
    -- Calcular costo por hora
    IF v_horas_totales > 0 THEN
        SET v_costo_hora = v_costo_nuevo / v_horas_totales;
    ELSE
        SET v_costo_hora = 0;
    END IF;
    
    -- Calcular valor remanente
    SET v_valor_remanente = v_costo_nuevo * (p_cocada_final / 100);
    
    -- Insertar registro de desecho
    INSERT INTO desechos (
        neumatico_id, fecha_desecho, horometro_final, cocada_final,
        porcentaje_desgaste_final, horas_totales_trabajadas, costo_hora,
        valor_remanente, motivo_desecho, mes_desecho, ano_desecho
    ) VALUES (
        p_neumatico_id, p_fecha_desecho, p_horometro_final, p_cocada_final,
        v_porcentaje_final, v_horas_totales, v_costo_hora,
        v_valor_remanente, p_motivo_desecho, MONTH(p_fecha_desecho), YEAR(p_fecha_desecho)
    );
    
    -- Actualizar estado del neumático
    UPDATE neumaticos SET estado = 'desechado' WHERE id = p_neumatico_id;
    
    -- Desactivar instalación actual
    UPDATE instalaciones SET activo = 0 
    WHERE neumatico_id = p_neumatico_id AND activo = 1;
    
    COMMIT;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_insertar_neumatico
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_insertar_neumatico`;
delimiter ;;
CREATE PROCEDURE `sp_insertar_neumatico`(IN p_codigo_interno VARCHAR(20),
    IN p_numero_serie VARCHAR(50),
    IN p_dot VARCHAR(20),
    IN p_marca_id INT,
    IN p_diseno_id INT,
    IN p_medida_id INT,
    IN p_nuevo_usado VARCHAR(5),
    IN p_remanente_nuevo DECIMAL(5,2),
    IN p_garantia_horas INT,
    IN p_vida_util_horas INT,
    IN p_costo_nuevo DECIMAL(10,2))
BEGIN
    INSERT INTO neumaticos (
        codigo_interno, numero_serie, dot, marca_id, diseno_id, medida_id,
        nuevo_usado, remanente_nuevo, garantia_horas, vida_util_horas, costo_nuevo
    ) VALUES (
        p_codigo_interno, p_numero_serie, p_dot, p_marca_id, p_diseno_id, p_medida_id,
        p_nuevo_usado, p_remanente_nuevo, p_garantia_horas, p_vida_util_horas, p_costo_nuevo
    );
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_instalar_neumatico
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_instalar_neumatico`;
delimiter ;;
CREATE PROCEDURE `sp_instalar_neumatico`(IN p_neumatico_id INT,
    IN p_equipo_id INT,
    IN p_posicion TINYINT,
    IN p_fecha_instalacion DATE,
    IN p_horometro_instalacion INT,
    IN p_cocada_inicial DECIMAL(5,2),
    IN p_observaciones TEXT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Desactivar instalación anterior si existe
    UPDATE instalaciones 
    SET activo = 0 
    WHERE neumatico_id = p_neumatico_id AND activo = 1;
    
    -- Crear nueva instalación
    INSERT INTO instalaciones (
        neumatico_id, equipo_id, posicion, fecha_instalacion,
        horometro_instalacion, cocada_inicial, observaciones
    ) VALUES (
        p_neumatico_id, p_equipo_id, p_posicion, p_fecha_instalacion,
        p_horometro_instalacion, p_cocada_inicial, p_observaciones
    );
    
    -- Actualizar estado del neumático
    UPDATE neumaticos SET estado = 'instalado' WHERE id = p_neumatico_id;
    
    -- Registrar movimiento
    INSERT INTO movimientos (
        neumatico_id, equipo_destino_id, posicion_destino, fecha_movimiento,
        horometro_movimiento, tipo_movimiento, cocada_movimiento
    ) VALUES (
        p_neumatico_id, p_equipo_id, p_posicion, p_fecha_instalacion,
        p_horometro_instalacion, 'instalacion', p_cocada_inicial
    );
    
    COMMIT;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_obtener_alertas_pendientes
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_obtener_alertas_pendientes`;
delimiter ;;
CREATE PROCEDURE `sp_obtener_alertas_pendientes`()
BEGIN
    SELECT 
        a.id,
        a.tipo_alerta,
        a.descripcion,
        a.fecha_alerta,
        a.prioridad,
        e.codigo as equipo_codigo,
        n.codigo_interno as neumatico_codigo,
        i.posicion
    FROM alertas a
    JOIN instalaciones i ON a.instalacion_id = i.id
    JOIN equipos e ON i.equipo_id = e.id
    JOIN neumaticos n ON i.neumatico_id = n.id
    WHERE a.estado = 'pendiente'
    ORDER BY 
        FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'),
        a.fecha_alerta DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_registrar_seguimiento_semanal
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_registrar_seguimiento_semanal`;
delimiter ;;
CREATE PROCEDURE `sp_registrar_seguimiento_semanal`(IN p_instalacion_id INT,
    IN p_fecha_medicion DATE,
    IN p_cocada_actual DECIMAL(5,2),
    IN p_horas_trabajadas INT,
    IN p_observaciones TEXT)
BEGIN
    DECLARE v_cocada_inicial DECIMAL(5,2);
    DECLARE v_desgaste_semanal DECIMAL(5,2);
    DECLARE v_porcentaje_desgaste DECIMAL(5,2);
    DECLARE v_semana INT;
    DECLARE v_ano INT;

    -- Obtener cocada inicial
    SELECT cocada_inicial INTO v_cocada_inicial
    FROM instalaciones WHERE id = p_instalacion_id;
    
    -- Calcular desgaste
    SET v_desgaste_semanal = v_cocada_inicial - p_cocada_actual;
    SET v_porcentaje_desgaste = (v_desgaste_semanal / v_cocada_inicial) * 100;
    
    -- Obtener semana y año
    SET v_semana = WEEK(p_fecha_medicion);
    SET v_ano = YEAR(p_fecha_medicion);
    
    INSERT INTO seguimiento_semanal (
        instalacion_id, fecha_medicion, semana, ano, cocada_actual,
        desgaste_semanal, horas_trabajadas, porcentaje_desgaste, observaciones
    ) VALUES (
        p_instalacion_id, p_fecha_medicion, v_semana, v_ano, p_cocada_actual,
        v_desgaste_semanal, p_horas_trabajadas, v_porcentaje_desgaste, p_observaciones
    );
    
    -- Verificar alertas del modelo 30-30-30
    CALL sp_verificar_alertas_rotacion(p_instalacion_id, v_porcentaje_desgaste);
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_reporte_mensual_equipo
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_reporte_mensual_equipo`;
delimiter ;;
CREATE PROCEDURE `sp_reporte_mensual_equipo`(IN p_equipo_id INT,
    IN p_mes INT,
    IN p_ano INT)
BEGIN
    SELECT 
        e.codigo as equipo_codigo,
        e.nombre as equipo_nombre,
        n.codigo_interno,
        n.numero_serie,
        m.nombre as marca,
        d.nombre as diseno,
        med.medida,
        i.posicion,
        i.cocada_inicial,
        COALESCE(MAX(ss.cocada_actual), i.cocada_inicial) as cocada_actual,
        COALESCE(SUM(ss.horas_trabajadas), 0) as horas_acumuladas,
        COALESCE(MAX(ss.porcentaje_desgaste), 0) as porcentaje_desgaste,
        (100 - COALESCE(MAX(ss.porcentaje_desgaste), 0)) as vida_util_restante,
        n.costo_nuevo,
        (n.costo_nuevo * (100 - COALESCE(MAX(ss.porcentaje_desgaste), 0)) / 100) as valor_remanente
    FROM equipos e
    JOIN instalaciones i ON e.id = i.equipo_id AND i.activo = 1
    JOIN neumaticos n ON i.neumatico_id = n.id
    JOIN marcas m ON n.marca_id = m.id
    JOIN disenos d ON n.diseno_id = d.id
    JOIN medidas med ON n.medida_id = med.id
    LEFT JOIN seguimiento_semanal ss ON i.id = ss.instalacion_id 
        AND MONTH(ss.fecha_medicion) = p_mes 
        AND YEAR(ss.fecha_medicion) = p_ano
    WHERE e.id = p_equipo_id
    GROUP BY n.id, i.id
    ORDER BY i.posicion;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_verificar_alertas_rotacion
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_verificar_alertas_rotacion`;
delimiter ;;
CREATE PROCEDURE `sp_verificar_alertas_rotacion`(IN p_instalacion_id INT,
    IN p_porcentaje_desgaste DECIMAL(5,2))
BEGIN
    DECLARE v_posicion TINYINT;
    DECLARE v_limite_desgaste DECIMAL(5,2);
    DECLARE v_alerta_existente INT DEFAULT 0;
    
    -- Obtener posición actual
    SELECT posicion INTO v_posicion
    FROM instalaciones WHERE id = p_instalacion_id;
    
    -- Definir límite según modelo 30-30-30
    CASE 
        WHEN v_posicion IN (1, 2) THEN SET v_limite_desgaste = 30.0; -- Delanteras
        WHEN v_posicion IN (5, 6) THEN SET v_limite_desgaste = 30.0; -- Posteriores
        WHEN v_posicion IN (3, 4) THEN SET v_limite_desgaste = 30.0; -- Intermedias
        ELSE SET v_limite_desgaste = 90.0; -- Límite final
    END CASE;
    
    -- Verificar si ya existe alerta
    SELECT COUNT(*) INTO v_alerta_existente
    FROM alertas 
    WHERE instalacion_id = p_instalacion_id 
    AND tipo_alerta = 'rotacion_30' 
    AND estado = 'pendiente';
    
    -- Generar alerta si supera el límite y no existe una pendiente
    IF p_porcentaje_desgaste >= v_limite_desgaste AND v_alerta_existente = 0 THEN
        INSERT INTO alertas (instalacion_id, tipo_alerta, descripcion, fecha_alerta, prioridad)
        VALUES (
            p_instalacion_id,
            'rotacion_30',
            CONCAT('Neumático en posición ', v_posicion, ' supera ', v_limite_desgaste, '% de desgaste (', p_porcentaje_desgaste, '%). Requiere rotación.'),
            CURDATE(),
            'alta'
        );
    END IF;
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
