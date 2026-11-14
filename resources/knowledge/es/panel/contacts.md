---
id: contacts.panel.contacts
title: Contactos y acuerdos
summary: Cómo anotar organizaciones, personas, historial y acuerdos, qué avisos llegan y qué datos conviene guardar.
category: activities
audience: panel
order: 80
keywords: [contactos, organizaciones, proveedores, tiendas, patrocinadores, personas de contacto, historial, reuniones, llamadas, acuerdos, convenios, descuentos, revisión, recordatorio, aviso, vínculos, archivar, borrar, datos personales, protección de datos, rgpd]
conditions:
  permissions:
    any: ['contacts:contacts.view_any', 'contacts:contacts.manage']
---
**Contactos** guarda con quién se relaciona la asociación fuera de ella: tiendas, proveedores, instituciones, patrocinadores, medios... Está en el menú **Personas → Contactos**, y los acuerdos, en **Acuerdos**, debajo.

Quien tiene permiso para **ver** contactos lo ve todo sin poder cambiarlo. Para anotar, editar, archivar o borrar hace falta el permiso para **gestionar** contactos.

## Organizaciones y personas

1. En **Contactos** pulsa **Nueva organización**.
2. Pon el nombre y el **Tipo** (proveedor, tienda, asociación, institución, patrocinador, medio de comunicación u otro). Rellena solo lo que sepas: sitio web, correo, teléfono, dirección y notas.
3. Guarda. En la página de la organización, la pestaña **Personas de contacto** guarda a quien atiende a la asociación allí, con su cargo, correo, teléfono y notas.

La lista se puede filtrar por **Tipo** y por **Estado**.

## Archivar o borrar

- **Archivar** es lo normal cuando la relación ya no está activa. La organización deja de salir en la lista por defecto, pero se conserva todo: personas, historial, acuerdos y vínculos. Puedes **desarchivarla** cuando quieras.
- **Borrar** elimina la organización y, con ella, todas sus personas, su historial, sus acuerdos y sus vínculos. No se puede deshacer, y por eso pide marcar una casilla de confirmación.

> [!WARNING]
> Si dudas, archiva. Un acuerdo terminado se marca como **Terminado**; no hace falta borrarlo.

Borrar una persona de contacto la elimina del todo. Sus entradas del historial se quedan sin persona, pero el texto que escribiste en ellas puede seguir nombrándola.

## Historial

La pestaña **Historial** de la organización es el cuaderno de la relación: reuniones, llamadas, correos y mensajes. Cada entrada lleva el día, el tipo, la persona (si fue con alguien concreto), el acuerdo al que se refiere (si lo hay) y **Qué se habló**. Guarda quién la escribió.

Anota lo justo para que otra persona de la junta entienda en qué quedó la conversación sin tener que preguntarte.

## Acuerdos

Un acuerdo es un pacto informal con una organización: un descuento, una cesión de espacio, un patrocinio, una colaboración. No es un contrato ni sustituye a uno.

1. Entra en **Acuerdos** y pulsa **Nuevo acuerdo**, o usa la pestaña **Acuerdos** de la organización.
2. Elige la **Organización**, el **Título** y el **Tipo**.
3. Opcionalmente, pon fecha de inicio y de fin, las **Condiciones**, un **Enlace al documento** (debe empezar por `https://`) y un **Responsable**.

El **Estado** puede ser **Activo**, **En pausa** o **Terminado**.

> [!IMPORTANT]
> La organización de un acuerdo no se puede cambiar una vez creado. Si te equivocaste, crea el acuerdo de nuevo en la organización correcta y borra el erróneo.

### Fecha de revisión y aviso

La **Revisión** es el día en que conviene volver a mirar el acuerdo. Cuando llega:

- Se avisa **una sola vez por fecha**, por la campana de la web y por correo (si la persona no ha desactivado la categoría **Revisión de acuerdos** en sus preferencias de correo; también puede recibirlo en el resumen diario).
- Se avisa solo si el acuerdo está **Activo** o **En pausa** y su organización no está archivada.
- Lo recibe el **Responsable**, si sigue pudiendo gestionar contactos. Si no hay responsable, o ya no puede gestionarlos, lo reciben todas las personas que pueden.
- La comprobación se hace cada mañana. Si el envío falla, o no hay nadie a quien avisar, se vuelve a intentar al día siguiente: el aviso puede llegar más tarde, pero no se pierde.

Para decir «ya lo he revisado», pon una **nueva fecha de revisión**: se avisará de nuevo cuando llegue. Con la misma fecha no vuelve a avisar. En la lista de acuerdos, el filtro **Revisión pendiente** enseña los que ya han llegado a su fecha.

## Vínculos

En la pestaña **Vínculos** puedes relacionar una organización o un acuerdo con otros registros del sitio, como un evento o un torneo: pulsa **Vincular registro**, elige el tipo y busca el registro. Solo salen los que tú puedes ver. **Quitar** deshace el vínculo sin tocar el registro vinculado.

A veces se ve una línea como «2 vínculos no disponibles». Significa que hay vínculos que tú no puedes ver: el registro es de un módulo que ahora está desactivado, o tu cuenta no tiene permiso para verlo. No se muestra qué son para no revelar lo que no te corresponde; otra persona con más permisos sí los verá.

## Qué datos conviene guardar

Las personas de contacto son terceras personas que no han pedido estar aquí. Guarda lo mínimo útil:

- **Sí**: nombre, cargo, y el correo o teléfono **profesional** con el que contactan con la asociación.
- **Mejor no**: teléfonos o direcciones particulares, datos de salud, ideas políticas o religiosas, opiniones personales sobre alguien, ni nada que no dirías delante de la propia persona.
- **Notas e historial**: hechos y acuerdos, no juicios. Una frase como «prefiere que se le escriba por la mañana» sobra o basta, según el caso.
- Borra o archiva lo que ya no haga falta. Si alguien te pide que quites sus datos, borra a la persona de contacto y revisa el texto del historial.

## Si algo no sale bien

- **No veo el botón para crear o editar**: tu cuenta solo tiene permiso para ver contactos. Pide a la administración el permiso para gestionarlos.
- **No me llegó el aviso de un acuerdo**: comprueba que está **Activo** o **En pausa**, que tiene fecha de revisión, que su organización no está archivada y que tienes activada la categoría **Revisión de acuerdos** en tus preferencias.
- **No puedo elegir a una persona como responsable**: solo salen quienes pueden gestionar contactos.
