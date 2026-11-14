# Contactos

Módulo de [GuildForge](https://github.com/DavidMRGaona/guildforge) para llevar los contactos de la asociación. Solo existe en el panel: no añade páginas públicas.

## Qué hace

- **Organizaciones** (proveedores, tiendas, instituciones, patrocinadores, medios...) con sus datos de contacto. Se pueden **archivar** (se conserva todo) o **borrar** (se borran también sus personas, historial, acuerdos y vínculos; pide doble confirmación).
- **Personas de contacto** de cada organización.
- **Historial** de la relación: reuniones, llamadas, correos y mensajes, con su autor.
- **Acuerdos** informales con una organización: tipo, estado (activo, en pausa, terminado), fechas opcionales, condiciones, enlace https al documento, responsable y **fecha de revisión**. La organización de un acuerdo no cambia una vez creado.
- **Vínculos** con registros de otros módulos y del core (eventos, torneos...) mediante el contrato de registros vinculables del core. Los vínculos a registros que quien mira no puede ver, o de módulos desactivados, se ocultan y solo se cuenta cuántos hay.

Aparece en **Personas → Contactos**, con **Acuerdos** como entrada hija.

## Ayuda

La guía `contacts.panel.contacts` explica el uso del módulo en el centro de ayuda del panel. La ven solo quienes tienen alguno de los permisos del módulo, y se enlaza desde sus páginas.

## Permisos

Ningún rol los recibe por defecto: la administración los marca en el editor de roles.

| Permiso | Para qué |
|---|---|
| `contacts.view_any` («Ver los contactos») | Ver organizaciones, personas, historial, acuerdos y vínculos, sin poder cambiarlos. |
| `contacts.manage` («Gestionar los contactos») | Crear, editar, archivar y borrar todo lo anterior, y vincular registros. También decide quién puede ser responsable de un acuerdo y quién recibe los avisos de revisión. |

## Aviso de revisión de acuerdos

- Categoría de correo opcional `contacts.agreement-review` («Revisión de acuerdos»): campana del sitio y correo, apta para el resumen diario. Cada persona puede desactivarla en sus preferencias.
- Comando programado `contacts:send-agreement-reviews`, cada día a las 07:00 en la zona horaria de visualización del sitio.
- Avisa **una vez por fecha de revisión** de los acuerdos activos o en pausa de organizaciones no archivadas. Lo recibe el responsable si aún puede gestionar contactos; si no, todas las personas con ese permiso. Poner una nueva fecha de revisión es la forma de dar el acuerdo por revisado.
- El envío es **al menos una vez**: si falla o no hay a quién avisar, se reintenta en la siguiente ejecución.

## Requisitos

- Core de GuildForge `^3.68`.
- PHP 8.5 o superior, Laravel 13 o superior, Filament 5.

En desarrollo: todavía no hay ninguna versión publicada.
