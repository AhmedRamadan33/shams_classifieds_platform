<?php

declare(strict_types=1);

return [
    [
        'slug' => 'cars',
        'name' => 'سيارات',
        'icon' => 'car',
        'children' => [
            'cars-for-sale' => 'سيارات للبيع',
            'cars-for-rent' => 'سيارات للإيجار',
            'car-parts' => 'قطع غيار وإكسسوارات',
            'motorcycles' => 'دراجات نارية',
        ],
        'fields' => [
            ['key' => 'brand', 'name' => 'الماركة', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => [
                'تويوتا', 'هيونداي', 'كيا', 'نيسان', 'شيفروليه', 'مرسيدس', 'بي إم دبليو', 'أودي', 'فولكس فاجن', 'سكودا',
                'رينو', 'بيجو', 'سيتروين', 'فيات', 'سوزوكي', 'ميتسوبيشي', 'مازدا', 'هوندا', 'فورد', 'جيلي',
                'شيري', 'بي واي دي', 'إم جي', 'شانجان', 'دي إف إس كيه', 'أوبل', 'سيات', 'أخرى',
            ]],
            ['key' => 'model', 'name' => 'الموديل', 'type' => 'text', 'required' => true],
            ['key' => 'year', 'name' => 'سنة الصنع', 'type' => 'number', 'filterable' => true, 'required' => true],
            ['key' => 'mileage', 'name' => 'الكيلومترات', 'type' => 'number', 'unit' => 'كم', 'filterable' => true],
            ['key' => 'transmission', 'name' => 'ناقل الحركة', 'type' => 'select', 'filterable' => true, 'options' => ['أوتوماتيك', 'يدوي']],
            ['key' => 'fuel_type', 'name' => 'نوع الوقود', 'type' => 'select', 'filterable' => true, 'options' => ['بنزين', 'سولار', 'غاز', 'كهرباء']],
            ['key' => 'condition', 'name' => 'الحالة', 'type' => 'select', 'filterable' => true, 'options' => ['جديدة', 'مستعملة']],
        ],
    ],
    [
        'slug' => 'real-estate',
        'name' => 'عقارات',
        'icon' => 'building',
        'children' => [
            'apartments-for-sale' => 'شقق للبيع',
            'apartments-for-rent' => 'شقق للإيجار',
            'villas' => 'فيلات',
            'land' => 'أراضي',
            'shops-and-offices' => 'محلات ومكاتب',
        ],
        'fields' => [
            ['key' => 'property_type', 'name' => 'نوع العقار', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => [
                'شقة', 'دوبلكس', 'بنتهاوس', 'استوديو', 'فيلا', 'تاون هاوس', 'أرض', 'محل تجاري', 'مكتب', 'مخزن', 'أخرى',
            ]],
            ['key' => 'area', 'name' => 'المساحة', 'type' => 'number', 'unit' => 'م²', 'filterable' => true, 'required' => true],
            ['key' => 'rooms', 'name' => 'عدد الغرف', 'type' => 'number', 'filterable' => true],
            ['key' => 'bathrooms', 'name' => 'عدد الحمامات', 'type' => 'number'],
            ['key' => 'floor', 'name' => 'الطابق', 'type' => 'number'],
            ['key' => 'finishing', 'name' => 'حالة التشطيب', 'type' => 'select', 'filterable' => true, 'options' => ['تشطيب كامل', 'نصف تشطيب', 'على الطوب']],
        ],
    ],
    [
        'slug' => 'jobs',
        'name' => 'وظائف',
        'icon' => 'briefcase',
        'children' => [
            'job-vacancies' => 'وظائف شاغرة',
            'job-seekers' => 'أبحث عن عمل',
        ],
        'fields' => [
            ['key' => 'job_type', 'name' => 'نوع الدوام', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => ['دوام كامل', 'دوام جزئي', 'عن بعد', 'تدريب']],
            ['key' => 'job_level', 'name' => 'المستوى الوظيفي', 'type' => 'select', 'filterable' => true, 'options' => ['مبتدئ', 'متوسط', 'خبير', 'مدير', 'تنفيذي']],
            ['key' => 'experience_years', 'name' => 'سنوات الخبرة', 'type' => 'number', 'filterable' => true],
            ['key' => 'education', 'name' => 'المؤهل', 'type' => 'select', 'options' => ['بدون مؤهل', 'مؤهل متوسط', 'دبلوم', 'مؤهل عالي', 'ماجستير', 'دكتوراه']],
        ],
    ],
    [
        'slug' => 'electronics',
        'name' => 'إلكترونيات',
        'icon' => 'device',
        'children' => [
            'mobiles' => 'موبايلات',
            'computers-and-laptops' => 'كمبيوتر ولابتوب',
            'tvs-and-screens' => 'تلفزيونات وشاشات',
            'cameras' => 'كاميرات',
            'video-games' => 'ألعاب فيديو',
        ],
        'fields' => [
            ['key' => 'condition', 'name' => 'الحالة', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => ['جديد', 'مستعمل']],
            ['key' => 'brand', 'name' => 'الماركة', 'type' => 'text', 'filterable' => true],
            ['key' => 'warranty', 'name' => 'الضمان', 'type' => 'boolean'],
        ],
    ],
    [
        'slug' => 'home',
        'name' => 'أثاث ومنزل',
        'icon' => 'home',
        'children' => [
            'furniture' => 'أثاث',
            'home-appliances' => 'أجهزة كهربائية',
            'decor-and-housewares' => 'ديكور وأدوات منزلية',
        ],
        'fields' => [
            ['key' => 'condition', 'name' => 'الحالة', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => ['جديد', 'مستعمل']],
        ],
    ],
    [
        'slug' => 'services',
        'name' => 'خدمات',
        'icon' => 'wrench',
        'children' => [
            'maintenance-and-finishing' => 'صيانة وتشطيبات',
            'transport-and-shipping' => 'نقل وشحن',
            'lessons-and-courses' => 'دروس ودورات',
            'events-and-photography' => 'مناسبات وتصوير',
            'other-services' => 'خدمات أخرى',
        ],
        'fields' => [
            ['key' => 'service_type', 'name' => 'نوع الخدمة', 'type' => 'text', 'required' => true],
            ['key' => 'service_area', 'name' => 'منطقة الخدمة', 'type' => 'text'],
        ],
    ],
    [
        'slug' => 'fashion',
        'name' => 'موضة وجمال',
        'icon' => 'shirt',
        'children' => [
            'clothes' => 'ملابس',
            'shoes-and-bags' => 'أحذية وحقائب',
            'watches-and-jewelry' => 'ساعات ومجوهرات',
        ],
        'fields' => [
            ['key' => 'condition', 'name' => 'الحالة', 'type' => 'select', 'filterable' => true, 'required' => true, 'options' => ['جديد', 'مستعمل']],
            ['key' => 'size', 'name' => 'المقاس', 'type' => 'text'],
        ],
    ],
    [
        'slug' => 'other',
        'name' => 'أخرى',
        'icon' => 'dots',
        'children' => [],
        'fields' => [],
    ],
];
