import { ref } from 'vue';
import { useSnackbar } from '@/Composables/useSnackbar';

export function useDocumentImages() {
    const { showSnackbar } = useSnackbar();
    const headerImages = ref([null, null, null]);
    const footerImages = ref([null, null, null]);

    function normalizeImages(images = []) {
        const slots = [null, null, null];

        images.forEach((img) => {
            const index =
                img.position === 'left' ? 0 : img.position === 'center' ? 1 : img.position === 'right' ? 2 : null;

            if (index === null) return;

            slots[index] = {
                id: img.id,
                url: `/storage/${img.path}`,
                file: null,
                removed: false,
            };
        });

        return slots;
    }

    function handleImage(event, type, index) {
        const file = event.target.files?.[0];

        if (!file) return;

        if (file.size > 10 * 1024 * 1024) {
            showSnackbar('A imagem selecionada excede o limite máximo permitido de 10MB.', 'error');
            event.target.value = '';
            return;
        }

        const image = {
            file,
            url: URL.createObjectURL(file),
            id: null,
            removed: false,
        };

        if (type === 'header') {
            headerImages.value[index] = image;
        } else {
            footerImages.value[index] = image;
        }
    }

    function removeImage(type, index) {
        const target = type === 'header' ? headerImages : footerImages;

        const current = target.value[index];

        if (current?.id) {
            current.removed = true;
            current.file = null;
            current.url = null;
        } else {
            target.value[index] = null;
        }
    }

    function appendImages(payload, key, images) {
        images.forEach((img, index) => {
            if (!img) {
                payload.append(`${key}[${index}][_delete]`, '1');
                return;
            }

            if (img.file) {
                payload.append(`${key}[${index}][file]`, img.file);
            }

            if (img.id) {
                payload.append(`${key}[${index}][id]`, img.id);
            }

            if (img.removed) {
                payload.append(`${key}[${index}][_delete]`, '1');
            }
        });
    }

    function resetImages() {
        headerImages.value = [null, null, null];
        footerImages.value = [null, null, null];
    }

    return {
        headerImages,
        footerImages,
        normalizeImages,
        handleImage,
        removeImage,
        appendImages,
        resetImages,
    };
}
