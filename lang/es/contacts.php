<?php

declare(strict_types=1);

return [
    'navigation' => [
        'contacts' => 'Contactos',
        'agreements' => 'Acuerdos',
    ],

    'model' => [
        'organization' => [
            'singular' => 'organización',
            'plural' => 'Contactos',
        ],
        'person' => [
            'singular' => 'persona de contacto',
            'plural' => 'personas de contacto',
        ],
        'interaction' => [
            'singular' => 'entrada del historial',
            'plural' => 'entradas del historial',
        ],
        'agreement' => [
            'singular' => 'acuerdo',
            'plural' => 'Acuerdos',
        ],
    ],

    'pages' => [
        'create_organization' => 'Nueva organización',
        'edit_organization' => 'Editar organización',
        'view_organization' => 'Organización',
        'create_agreement' => 'Nuevo acuerdo',
        'edit_agreement' => 'Editar acuerdo',
        'view_agreement' => 'Acuerdo',
    ],

    'sections' => [
        'data' => 'Datos de la organización',
        'contact' => 'Cómo contactar',
        'people' => 'Personas de contacto',
        'people_description' => 'Opcional. Añade a quien atiende a la asociación en esta organización; después se gestionan en la pestaña «Personas de contacto» de su ficha.',
    ],

    'fields' => [
        'name' => 'Nombre',
        'type' => 'Tipo',
        'status' => 'Estado',
        'website' => 'Sitio web',
        'email' => 'Correo electrónico',
        'phone' => 'Teléfono',
        'address' => 'Dirección',
        'notes' => 'Notas',
        'role' => 'Cargo',
        'people_count' => 'Personas',
        'title' => 'Título',
        'organization' => 'Organización',
        'kind' => 'Tipo',
        'starts_on' => 'Fecha de inicio',
        'ends_on' => 'Fecha de fin',
        'review_on' => 'Revisión',
        'responsible' => 'Responsable',
        'document_url' => 'Enlace al documento',
        'conditions' => 'Condiciones',
    ],

    'filters' => [
        'type' => 'Tipo',
        'status' => 'Estado',
        'kind' => 'Tipo de acuerdo',
        'review_due' => 'Revisión pendiente',
    ],

    'hints' => [
        'review_on' => 'Cuándo conviene volver a mirar el acuerdo. Se avisa una vez por fecha; poner una nueva es el gesto de «lo he revisado».',
        'responsible' => 'Solo personas con permiso para gestionar contactos.',
        'responsible_lost' => ':name ya no puede gestionar contactos. Los avisos de revisión van a todas las personas que sí pueden; elige a otra persona si quieres que vuelva a haber un responsable.',
        'responsible_lost_option' => ':name (sin permiso)',
        'document_url' => 'Enlace seguro (https) al documento firmado o a la carpeta donde está.',
    ],

    'validation' => [
        'responsible' => 'Elige a una persona que pueda gestionar contactos.',
        'document_url' => 'El enlace debe empezar por https://.',
    ],

    'enums' => [
        'type' => [
            'supplier' => 'Proveedor',
            'shop' => 'Tienda',
            'association' => 'Asociación',
            'institution' => 'Institución',
            'sponsor' => 'Patrocinador',
            'media' => 'Medio de comunicación',
            'other' => 'Otro',
        ],
        'status' => [
            'active' => 'Activa',
            'archived' => 'Archivada',
        ],
        'interaction_kind' => [
            'meeting' => 'Reunión',
            'call' => 'Llamada',
            'email' => 'Correo',
            'message' => 'Mensaje',
            'other' => 'Otro',
        ],
        'agreement_kind' => [
            'discount' => 'Descuento',
            'space' => 'Cesión de espacio',
            'sponsorship' => 'Patrocinio',
            'collaboration' => 'Colaboración',
            'other' => 'Otro',
        ],
        'agreement_status' => [
            'active' => 'Activo',
            'paused' => 'En pausa',
            'ended' => 'Terminado',
        ],
    ],

    'actions' => [
        'archive' => 'Archivar',
        'unarchive' => 'Desarchivar',
        'archive_heading' => 'Archivar la organización',
        'archive_description' => 'Dejará de salir en la lista por defecto, pero se conservan sus personas y todo lo demás. Puedes desarchivarla cuando quieras.',
        'unarchive_heading' => 'Desarchivar la organización',
        'unarchive_description' => 'Volverá a salir en la lista por defecto.',
        'archived' => 'Organización archivada',
        'unarchived' => 'Organización desarchivada',
        'delete_confirmation_check' => 'Entiendo que esto no se puede deshacer',
    ],

    'delete' => [
        'organization_heading' => 'Borrar la organización',
        'organization_description' => 'Se borrará la organización con todas sus personas, su historial, sus acuerdos y sus vínculos, y no se podrá recuperar. Si solo quieres dejar de verla en la lista, archívala: se conserva todo.',
        'organization_check' => 'Entiendo que se borrarán también sus personas, historial, acuerdos y vínculos, y que no se puede deshacer',
        'person_heading' => 'Borrar la persona de contacto',
        'person_description' => 'Se borrará del todo. Sus entradas del historial se quedan sin persona, pero el texto libre del historial puede seguir nombrándola.',
        'person_check' => 'Entiendo que se borrará del todo y que no se puede deshacer',
        'agreement_heading' => 'Borrar el acuerdo',
        'agreement_description' => 'Se borrará el acuerdo con sus condiciones, fechas y vínculos, y no se podrá recuperar. Si ya no está vigente, márcalo como terminado: se conserva.',
        'agreement_check' => 'Entiendo que se borrará del todo y que no se puede deshacer',
    ],

    'interaction' => [
        'occurred_on' => 'Día',
        'kind' => 'Tipo',
        'person' => 'Persona',
        'author' => 'Autor',
        'body' => 'Qué se habló',
        'no_person' => 'Sin persona concreta',
        'agreement' => 'Acuerdo',
        'no_agreement' => 'Sin acuerdo concreto',
    ],

    'relation_managers' => [
        'people' => [
            'title' => 'Personas de contacto',
            'create' => 'Añadir persona',
            'empty_state' => 'Sin personas de contacto',
            'empty_state_description' => 'Añade a quien atiende a la asociación en esta organización.',
        ],
        'interactions' => [
            'title' => 'Historial',
            'create' => 'Añadir entrada',
            'empty_state' => 'Sin entradas en el historial',
            'empty_state_description' => 'Anota reuniones, llamadas y mensajes para que la relación no dependa de la memoria de nadie.',
            'delete_heading' => 'Borrar la entrada del historial',
            'delete_description' => 'Se borrará del todo y no se podrá recuperar.',
        ],
        'agreements' => [
            'title' => 'Acuerdos',
            'create' => 'Añadir acuerdo',
            'empty_state' => 'Sin acuerdos',
            'empty_state_description' => 'Anota aquí lo que se acuerde con esta organización y cuándo conviene revisarlo.',
        ],
    ],

    'agreement_review' => [
        'section' => 'Contactos',
        'label' => 'Revisión de acuerdos',
        'description' => 'Aviso cuando llega la fecha de revisión de un acuerdo, para quien lo gestiona.',
        'notice' => 'Toca revisar el acuerdo «:agreement» con :organization (:date)',
        'mail' => [
            'subject' => 'Toca revisar el acuerdo «:agreement»',
            'greeting' => 'Hola, :name',
            'body' => 'El acuerdo «:agreement» con :organization llegó a su fecha de revisión (:date).',
            'hint' => 'Revísalo y, si sigue en marcha, pon una nueva fecha de revisión: es la forma de decir que ya lo has mirado.',
            'action' => 'Ver el acuerdo',
        ],
    ],

    'links' => [
        'title' => 'Vínculos',
        'singular' => 'vínculo',
        'plural' => 'vínculos',
        'record' => 'Registro',
        'type' => 'Tipo',
        'date' => 'Fecha',
        'link' => 'Vincular registro',
        'link_heading' => 'Vincular un registro',
        'link_description' => 'Elige qué tipo de registro quieres vincular y búscalo.',
        'record_type' => 'Tipo de registro',
        'record_id' => 'Registro',
        'record_id_hint' => 'Solo salen los registros que puedes ver.',
        'submit' => 'Vincular',
        'linked' => 'Registro vinculado',
        'already_linked' => 'Ese registro ya estaba vinculado',
        'unavailable' => 'Ese registro no está disponible',
        'not_allowed' => 'No puedes vincular registros',
        'delete' => 'Quitar',
        'delete_heading' => 'Quitar el vínculo',
        'delete_description' => 'Se quita el vínculo; el registro vinculado no cambia.',
        'deleted' => 'Vínculo quitado',
        'empty_state' => 'Sin vínculos',
        'empty_state_description' => 'Vincula eventos, torneos y otros registros del sitio relacionados con este contacto.',
        // Neutral on purpose: never the type or title of what cannot be seen
        'unavailable_count' => '{1} :count vínculo no disponible|[2,*] :count vínculos no disponibles',
    ],
];
