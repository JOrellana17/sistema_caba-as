# Preparar el proyecto para Codex en el IDE

## Colocacion de archivos

Coloca estos cuatro archivos juntos en la raiz de tu proyecto Laravel, al mismo nivel que `artisan`, `composer.json` y la carpeta `app`:

- `AGENTS.md`
- `SYSTEM_SPEC_CEC_RECONSTRUCCION.md`
- `INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md`
- `ARRANQUE_CODEX.md`

El nombre `AGENTS.md` debe conservarse exactamente. Si ya existe un archivo con ese nombre, integra estas reglas con las existentes y resuelve las contradicciones antes de reemplazarlo.

Abre esa carpeta del proyecto en tu IDE. No abras solamente la carpeta que contiene los documentos si la aplicacion Laravel esta en otra ubicacion.

El paquete no incluye una instalacion Laravel ni implementa el sistema: contiene las instrucciones y especificaciones para construirlo.

## Base de datos actual

La aplicacion y las pruebas utilizan **MySQL**, por decision del usuario del 2026-10-01. Conservar `sistema_cabanas` y sus datos. PHPUnit utiliza esquemas nuevos y aislados con prefijo `sistema_cabanas_pruebas_`; no configurar SQLite ni ejecutar pruebas sobre la base del sistema. El archivo SQLite anterior y las menciones en verificaciones antiguas se conservan como historial. Revisar `docs/PLAN_CAMBIO_MYSQL.md` antes de continuar.

## Prompt inicial para pegar en Codex

```text
Trabaja sobre este proyecto para reconstruir el sistema administrativo de Cabanas El Capitan.

Lee AGENTS.md, INSTRUCCIONES_AGENTE_CONSTRUCTOR_CEC.md y SYSTEM_SPEC_CEC_RECONSTRUCCION.md completos antes de implementar. Para el sistema nuevo prevalecen las decisiones de las secciones 72 en adelante, incluida la busqueda de clientes de la seccion 86. Las secciones anteriores describen el legado.

Primero inspecciona la plantilla existente y sus dependencias. Si Laravel ya esta instalado, utiliza esta misma aplicacion; no crees otra dentro. Sigue Blade + Bootstrap 5 segun AGENTS.md y conserva las convenciones necesarias del framework.

Crea docs/PLAN_IMPLEMENTACION.md y docs/DECISIONES.md. Identifica contradicciones y decisiones pendientes sin inventar reglas del negocio. Luego comienza la implementacion por fases en el orden del documento de instrucciones. No te limites a entregar otro plan: implementa las partes que ya estan definidas.

Para cada fase, verifica migraciones, modelos, reglas, permisos, validaciones, interfaz y pruebas aplicables antes de continuar. Actualiza el seguimiento y resume los resultados reales.

No agregues funcionalidades fuera de la especificacion. Si una duda afecta dinero, datos, seguridad o reglas de operacion, consulta esa duda y sigue trabajando en las partes independientes. No borres datos ni sustituyas trabajo existente sin autorizacion.
```

## Si continuas en una conversacion nueva

```text
Lee AGENTS.md y los dos documentos de especificacion. Revisa docs/PLAN_IMPLEMENTACION.md, docs/DECISIONES.md y el codigo real para identificar el estado actual. Continua desde la siguiente tarea pendiente, sin repetir etapas terminadas ni asumir que una verificacion pendiente ya se realizo. Manten el alcance y las decisiones aprobadas.
```

## Informacion que conviene tener disponible

No necesitas decidirla toda antes de comenzar la estructura base. Facilitala cuando corresponda al modulo afectado:

- MySQL aprobado; version y acceso del entorno de destino. Credenciales mediante la configuracion privada, nunca escritas en los Markdown.
- Datos reales de las cabanas, capacidades y tarifas aplicables.
- Horarios y reglas de entrada, salida, cancelacion y tratamiento de clientes inactivos o en lista negra.
- Reglas financieras y fiscales verificadas, moneda y criterio de redondeo.
- Roles del personal y permisos que debe recibir cada uno.
- Datos de prueba y servicios externos disponibles, cuando proceda implementar iCalendar o correo.

No incluyas contrasenas reales, claves privadas ni secretos en estos documentos. No autorices a inventar datos faltantes para dar por terminado un modulo.

## Archivos de seguimiento que generara Codex

- `docs/PLAN_IMPLEMENTACION.md`: estado de cada fase, comprobaciones realizadas y siguiente tarea.
- `docs/DECISIONES.md`: decisiones tecnicas, preguntas de negocio y respuestas aprobadas.

Estos archivos se generan tras inspeccionar tu proyecto. No se incluyen como planes ya completados ni como decisiones previamente aprobadas.
