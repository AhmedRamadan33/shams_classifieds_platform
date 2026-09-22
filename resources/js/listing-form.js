/**
 * Alpine component behind the "create / edit listing" form.
 *
 * Steps: 1 category, 2 details, 3 images, 4 review. All inputs live in one regular multipart
 * <form>, so submitting works like any other form; this component only drives the UI:
 * category drill-down, dynamic fields fetched per category, image previews, cover selection
 * and light client-side validation (the server always validates again).
 */
export default (config) => ({
    mode: config.mode,
    step: config.mode === 'edit' ? 2 : 1,
    totalSteps: 4,

    tree: config.tree,
    governorates: config.governorates,
    priceTypes: config.priceTypes,
    limits: config.limits,
    currency: config.currency,
    urls: config.urls,
    msg: config.messages,

    path: [],
    categoryId: config.values.categoryId,

    title: config.values.title,
    description: config.values.description,
    priceType: config.values.priceType,
    price: config.values.price,
    governorateId: config.values.governorateId,
    cityId: config.values.cityId,
    phone: config.values.phone,

    fieldDefs: [],
    fields: config.values.fields,
    loadingFields: false,
    fieldsError: false,

    existing: config.existingImages.map((image) => ({ ...image })),
    removed: [],
    newImages: [],
    uid: 0,
    coverKey: null,
    imageError: '',

    errors: config.errors || {},
    submitting: false,

    init() {
        if (this.categoryId) {
            this.path = this.findPath(this.categoryId) || [];
            this.loadFields();
        }

        if (config.values.cover && config.values.cover.startsWith('existing:')) {
            this.coverKey = 'e' + config.values.cover.split(':')[1];
        }

        // After a server-side validation error, open the earliest step that has an error.
        if (Object.keys(this.errors).length) {
            this.step = this.stepForErrors();
        }
    },

    // ------------------------------------------------------------- category

    findPath(id, nodes = this.tree, trail = []) {
        for (const node of nodes) {
            const current = [...trail, node];

            if (node.id === id) {
                return current;
            }

            const found = this.findPath(id, node.children, current);

            if (found) {
                return found;
            }
        }

        return null;
    },

    options() {
        return this.path.length ? this.path[this.path.length - 1].children : this.tree;
    },

    choose(node) {
        this.path.push(node);
        delete this.errors.category_id;

        if (node.children.length === 0) {
            this.categoryId = node.id;
            this.fieldDefs = [];
            this.loadFields();
            this.step = 2;
        } else {
            this.categoryId = null;
            this.fieldDefs = [];
        }
    },

    resetPathTo(index) {
        this.path = this.path.slice(0, index);
        this.categoryId = null;
        this.fieldDefs = [];
        this.step = 1;
    },

    categoryLabel() {
        return this.path.map((node) => node.name).join(' › ');
    },

    async loadFields() {
        if (!this.categoryId) {
            return;
        }

        this.loadingFields = true;
        this.fieldsError = false;

        try {
            const response = await fetch(this.urls.fields.replace('__ID__', this.categoryId), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('failed');
            }

            const data = await response.json();
            this.fieldDefs = data.fields;

            data.fields.forEach((field) => {
                if (!(field.key in this.fields)) {
                    this.fields[field.key] = field.type === 'boolean' ? '0' : '';
                }
            });
        } catch (error) {
            this.fieldsError = true;
        } finally {
            this.loadingFields = false;
        }
    },

    // -------------------------------------------------------------- details

    needsPrice() {
        const type = this.priceTypes.find((item) => item.value === this.priceType);

        return type ? type.needsPrice : true;
    },

    cities() {
        const governorate = this.governorates.find((item) => String(item.id) === String(this.governorateId));

        return governorate ? governorate.cities : [];
    },

    governorateName() {
        const governorate = this.governorates.find((item) => String(item.id) === String(this.governorateId));

        return governorate ? governorate.name : '';
    },

    cityName() {
        const city = this.cities().find((item) => String(item.id) === String(this.cityId));

        return city ? city.name : '';
    },

    priceLabel() {
        const type = this.priceTypes.find((item) => item.value === this.priceType);

        if (type && !type.needsPrice) {
            return type.label;
        }

        return this.price ? `${this.price} ${this.currency}` + (type ? ` — ${type.label}` : '') : '';
    },

    fieldDisplay(field) {
        const value = this.fields[field.key];

        if (value === '' || value === undefined || value === null) {
            return '';
        }

        if (field.type === 'boolean') {
            return value === '1' ? this.msg.yes : this.msg.no;
        }

        return field.unit ? `${value} ${field.unit}` : value;
    },

    // --------------------------------------------------------------- images

    imageList() {
        return [
            ...this.existing
                .filter((image) => !this.removed.includes(image.id))
                .map((image) => ({ key: 'e' + image.id, type: 'existing', id: image.id, url: image.url })),
            ...this.newImages.map((image) => ({ key: 'n' + image.uid, type: 'new', url: image.url, ref: image })),
        ];
    },

    coverItem() {
        const list = this.imageList();

        return list.find((image) => image.key === this.coverKey) || list[0] || null;
    },

    coverValue() {
        const cover = this.coverItem();

        if (!cover) {
            return '';
        }

        return cover.type === 'existing' ? `existing:${cover.id}` : `new:${this.newImages.indexOf(cover.ref)}`;
    },

    addFiles(event) {
        this.imageError = '';
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];

        for (const file of Array.from(event.target.files)) {
            if (!allowed.includes(file.type)) {
                this.imageError = this.msg.image_type.replace(':name', file.name);
                continue;
            }

            if (file.size > this.limits.maxImageKb * 1024) {
                this.imageError = this.msg.image_too_large
                    .replace(':name', file.name)
                    .replace(':size', Math.round(this.limits.maxImageKb / 1024));
                continue;
            }

            if (this.imageList().length >= this.limits.maxImages) {
                this.imageError = this.msg.image_max.replace(':max', this.limits.maxImages);
                break;
            }

            this.newImages.push({ uid: ++this.uid, file, url: URL.createObjectURL(file), name: file.name });
        }

        this.syncFileInput();
    },

    setCover(image) {
        this.coverKey = image.key;
    },

    removeImage(image) {
        if (image.type === 'existing') {
            this.removed.push(image.id);
        } else {
            URL.revokeObjectURL(image.url);
            this.newImages = this.newImages.filter((item) => item.uid !== image.ref.uid);
            this.syncFileInput();
        }

        if (this.coverKey === image.key) {
            this.coverKey = null;
        }
    },

    // The native file input must hold exactly the images we keep, so the browser submits them.
    syncFileInput() {
        const transfer = new DataTransfer();
        this.newImages.forEach((image) => transfer.items.add(image.file));
        this.$refs.fileInput.files = transfer.files;
    },

    // ----------------------------------------------------------- navigation

    validate(step) {
        const errors = {};
        const required = this.msg.required;

        if (step === 1 && !this.categoryId) {
            errors.category_id = this.msg.need_category;
        }

        if (step === 2) {
            if (this.title.trim().length < 5) {
                errors.title = this.msg.title_min;
            }

            if (this.description.trim().length < 20) {
                errors.description = this.msg.description_min;
            }

            if (this.needsPrice() && String(this.price).trim() === '') {
                errors.price = required;
            }

            if (String(this.governorateId) === '') {
                errors.governorate_id = required;
            }

            if (String(this.phone).trim() === '') {
                errors.phone = required;
            }

            this.fieldDefs.forEach((field) => {
                const value = this.fields[field.key];

                if (field.is_required && (value === '' || value === undefined || value === null)) {
                    errors['fields.' + field.key] = required;
                }
            });
        }

        return errors;
    },

    next() {
        const errors = this.validate(this.step);
        this.errors = errors;

        if (Object.keys(errors).length === 0) {
            this.step = Math.min(this.step + 1, this.totalSteps);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },

    back() {
        this.step = Math.max(this.step - 1, 1);
        this.errors = {};
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    goTo(step) {
        if (step < this.step) {
            this.errors = {};
            this.step = step;
        }
    },

    stepForErrors() {
        const steps = Object.keys(this.errors).map((key) => {
            if (key === 'category_id') return 1;
            if (key.startsWith('images') || key === 'cover' || key.startsWith('remove_images')) return 3;
            if (key === 'limit') return 4;

            return 2;
        });

        return Math.min(...steps);
    },

    onSubmit(event) {
        for (const step of [1, 2]) {
            const errors = this.validate(step);

            if (Object.keys(errors).length) {
                event.preventDefault();
                this.errors = errors;
                this.step = step;

                return;
            }
        }

        this.submitting = true;
    },

    error(key) {
        return this.errors[key] || '';
    },

    // Server errors for uploads come back per file ("images.0"); show the first one.
    imagesError() {
        if (this.imageError) {
            return this.imageError;
        }

        const key = Object.keys(this.errors).find((item) => item === 'images' || item.startsWith('images.'));

        return key ? this.errors[key] : '';
    },
});
