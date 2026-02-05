<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    pagination: {
        type: Object,
        required: true
    },
    baseUrl: {
        type: String,
        required: true
    },
    primaryColor: {
        type: String,
        default: '#3B82F6'
    }
});

const getPageUrl = (page) => {
    const separator = props.baseUrl.includes('?') ? '&' : '?';
    return `${props.baseUrl}${separator}page=${page}`;
};

const pages = () => {
    const total = props.pagination.total_pages;
    const current = props.pagination.page;
    const result = [];

    // Sempre mostra primeira página
    result.push(1);

    // Páginas ao redor da atual
    let start = Math.max(2, current - 2);
    let end = Math.min(total - 1, current + 2);

    if (start > 2) {
        result.push('...');
    }

    for (let i = start; i <= end; i++) {
        result.push(i);
    }

    if (end < total - 1) {
        result.push('...');
    }

    // Sempre mostra última página se houver mais de uma
    if (total > 1) {
        result.push(total);
    }

    return result;
};
</script>

<template>
    <div v-if="pagination.total_pages > 1" class="flex items-center justify-between px-4 py-3 border-t">
        <div class="text-sm text-gray-500">
            Mostrando {{ (pagination.page - 1) * pagination.per_page + 1 }} -
            {{ Math.min(pagination.page * pagination.per_page, pagination.total) }}
            de {{ pagination.total }}
        </div>

        <nav class="flex items-center space-x-1">
            <!-- Previous -->
            <Link
                v-if="pagination.page > 1"
                :href="getPageUrl(pagination.page - 1)"
                class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-md"
            >
                Anterior
            </Link>
            <span v-else class="px-3 py-1 text-sm text-gray-400">
                Anterior
            </span>

            <!-- Page numbers -->
            <template v-for="page in pages()" :key="page">
                <span v-if="page === '...'" class="px-2 py-1 text-sm text-gray-400">
                    ...
                </span>
                <Link
                    v-else-if="page !== pagination.page"
                    :href="getPageUrl(page)"
                    class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-md"
                >
                    {{ page }}
                </Link>
                <span
                    v-else
                    class="px-3 py-1 text-sm text-white rounded-md"
                    :style="{ backgroundColor: primaryColor }"
                >
                    {{ page }}
                </span>
            </template>

            <!-- Next -->
            <Link
                v-if="pagination.page < pagination.total_pages"
                :href="getPageUrl(pagination.page + 1)"
                class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded-md"
            >
                Próxima
            </Link>
            <span v-else class="px-3 py-1 text-sm text-gray-400">
                Próxima
            </span>
        </nav>
    </div>
</template>
