---
id: contacts.panel.contacts
title: Contactos y acuerdos
summary: Qué son las organizaciones, personas, historial, acuerdos y vínculos, qué avisos llegan y qué datos conviene guardar.
category: activities
audience: panel
order: 80
keywords: [contactos, organizaciones, proveedores, tiendas, patrocinadores, personas de contacto, historial, reuniones, llamadas, acuerdos, convenios, descuentos, revisión, recordatorio, aviso, vínculos, archivar, borrar, datos personales, protección de datos, rgpd]
related: [contacts.panel.manage-contacts]
conditions:
  permissions:
    any: ['contacts:contacts.view_any', 'contacts:contacts.manage']
---
**Contactos** guarda con quién se relaciona la asociación fuera de ella: tiendas, proveedores, instituciones, patrocinadores, medios... Está en el menú **Personas → Contactos**, y los acuerdos, en **Acuerdos**, debajo.

Quien tiene permiso para **ver** contactos lo ve todo sin poder cambiarlo. Para anotar, editar, archivar o borrar hace falta el permiso para **gestionar** contactos, y los pasos están en la guía «Anotar y cambiar contactos».

## Organizaciones y personas

Cada organización tiene un **Tipo** (proveedor, tienda, asociación, institución, patrocinador, medio de comunicación u otro) y las **Personas de contacto**: quien atiende a la asociación allí. Se pueden añadir al crear la organización, en la sección **Personas de contacto** del formulario, o más tarde en la pestaña **Personas de contacto** de su página, que es donde se editan después.

La lista se puede filtrar por **Tipo** y por **Estado**.

### Archivada o borrada

Una organización **archivada** deja de salir en la lista por defecto, pero conserva sus personas, su historial, sus acuerdos y sus vínculos, y se puede desarchivar. **Borrarla** elimina todo eso y no se puede deshacer. Una persona de contacto borrada se elimina del todo; sus entradas del historial se quedan sin persona, pero el texto que se escribió en ellas puede seguir nombrándola.

## Historial

La pestaña **Historial** de la organización es el cuaderno de la relación: reuniones, llamadas, correos y mensajes. Cada entrada lleva el día, el tipo, la persona (si fue con alguien concreto), el acuerdo al que se refiere (si lo hay) y **Qué se habló**. Guarda quién la escribió.

Anota lo justo para que otra persona de la junta entienda en qué quedó la conversación sin tener que preguntarte.

## Acuerdos

Un acuerdo es un pacto informal con una organización: un descuento, una cesión de espacio, un patrocinio, una colaboración. No es un contrato ni sustituye a uno.

Cada acuerdo tiene un **Tipo**, un **Estado**, fechas opcionales, **Condiciones**, un enlace al documento y un **Responsable**. La organización de un acuerdo no cambia una vez creado. El **Estado** puede ser **Activo**, **En pausa** o **Terminado**.

### Fecha de revisión y aviso

La **Revisión** es el día en que conviene volver a mirar el acuerdo. Cuando llega:

- Se avisa **una sola vez por fecha**, por la campana de la web y por correo (si la persona no ha desactivado la categoría **Revisión de acuerdos** en sus preferencias de correo; también puede recibirlo en el resumen diario).
- Se avisa solo si el acuerdo está **Activo** o **En pausa** y su organización no está archivada.
- Lo recibe el **Responsable**, si sigue pudiendo gestionar contactos. Si no hay responsable, o ya no puede gestionarlos, lo reciben todas las personas que pueden.
- La comprobación se hace cada mañana. Si el aviso no se puede encolar, o no hay nadie que pueda recibirlo, se vuelve a intentar a la mañana siguiente; en los demás casos se envía una sola vez por fecha. Si entretanto el acuerdo se termina o se borra, el aviso de la campana ya no se muestra.

Para dar un acuerdo por revisado, quien gestiona contactos pone una **nueva fecha de revisión**: se avisará de nuevo cuando llegue; con la misma fecha no vuelve a avisar. En la lista de acuerdos, el filtro **Revisión pendiente** enseña los que ya han llegado a su fecha.

## Vínculos

En la pestaña **Vínculos** puedes relacionar una organización o un acuerdo con otros registros del sitio, como un evento o un torneo: cada vínculo lleva al registro. Solo se muestran los que tú puedes ver.

A veces se ve una línea como «2 vínculos no disponibles». Significa que hay vínculos que tú no puedes ver: el registro es de un módulo que ahora está desactivado, tu cuenta no tiene permiso para verlo o el registro ya no existe. No se muestra qué son para no revelar lo que no te corresponde; otra persona con más permisos sí los verá.

## Qué datos conviene guardar

Las personas de contacto son terceras personas que no han pedido estar aquí. Guarda lo mínimo útil:

- **Sí**: nombre, cargo, y el correo o teléfono **profesional** con el que contactan con la asociación.
- **Mejor no**: teléfonos o direcciones particulares, datos de salud, ideas políticas o religiosas, opiniones personales sobre alguien, ni nada que no dirías delante de la propia persona.
- **Notas e historial**: hechos y acuerdos, no juicios. Una frase como «prefiere que se le escriba por la mañana» sobra o basta, según el caso.
- Borra o archiva lo que ya no haga falta. Si alguien te pide que quites sus datos, borra a la persona de contacto y revisa el texto del historial.

## Si algo no sale bien

- **No me llegó el aviso de un acuerdo**: comprueba que está **Activo** o **En pausa**, que tiene fecha de revisión, que su organización no está archivada y que tienes activada la categoría **Revisión de acuerdos** en tus preferencias.
- **No puedo elegir a una persona como responsable**: solo salen quienes pueden gestionar contactos.
