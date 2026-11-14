<?php

declare(strict_types=1);

return [
    'navigation' => [
        'contacts' => 'Contactos',
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
    ],

    'pages' => [
        'create_organization' => 'Nueva organización',
        'edit_organization' => 'Editar organización',
        'view_organization' => 'Organización',
    ],

    'sections' => [
        'data' => 'Datos de la organización',
        'contact' => 'Cómo contactar',
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
    ],

    'filters' => [
        'type' => 'Tipo',
        'status' => 'Estado',
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
    ],

    'relation_managers' => [
        'people' => [
            'title' => 'Personas de contacto',
            'create' => 'Añadir persona',
            'empty_state' => 'Sin personas de contacto',
            'empty_state_description' => 'Añade a quien atiende a la asociación en esta organización.',
        ],
    ],
];
