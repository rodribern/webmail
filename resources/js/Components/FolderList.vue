<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    folders: {
        type: Array,
        default: () => []
    },
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    primaryColor: {
        type: String,
        default: '#3B82F6'
    }
});

// Configuração de pastas com ícones e cores
const folderConfig = {
    'INBOX': {
        icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        color: '#3B82F6',
        name: 'Caixa de Entrada'
    },
    'Sent': {
        icon: 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8',
        color: '#10B981',
        name: 'Enviados'
    },
    'Drafts': {
        icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        color: '#F59E0B',
        name: 'Rascunhos'
    },
    'Trash': {
        icon: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        color: '#EF4444',
        name: 'Lixeira'
    },
    'Spam': {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        color: '#F97316',
        name: 'Spam'
    },
    'Junk': {
        icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        color: '#F97316',
        name: 'Lixo Eletrônico'
    },
};

const getConfig = (folder) => {
    // Verifica nome exato
    if (folderConfig[folder.name]) {
        return folderConfig[folder.name];
    }
    // Verifica parcial
    for (const [key, config] of Object.entries(folderConfig)) {
        if (folder.name.includes(key)) {
            return config;
        }
    }
    // Config padrão
    return {
        icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        color: '#6B7280',
        name: folder.name
    };
};

const getFolderUrl = (folder) => {
    if (folder.path === 'INBOX') {
        return '/mail/inbox';
    }
    return `/mail/folder/${encodeURIComponent(folder.path)}`;
};

const isActive = (folder) => {
    return folder.path === props.currentFolder;
};
</script>

<template>
    <nav class="space-y-1 px-4">
        <Link
            v-for="folder in folders"
            :key="folder.path"
            :href="getFolderUrl(folder)"
            class="group flex items-center px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200 ease-out"
            :class="{
                'shadow-lg scale-[1.02]': isActive(folder),
                'hover:bg-gray-50 hover:shadow hover:scale-[1.01]': !isActive(folder)
            }"
            :style="isActive(folder) ? {
                backgroundColor: getConfig(folder).color + '10',
                boxShadow: `0 4px 15px -3px ${getConfig(folder).color}40`
            } : {}"
        >
            <!-- Ícone colorido -->
            <div
                class="mr-3 p-1.5 rounded-lg transition-all duration-200"
                :style="{
                    backgroundColor: isActive(folder) ? getConfig(folder).color + '20' : 'transparent'
                }"
            >
                <svg
                    class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                    :style="{ color: getConfig(folder).color }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getConfig(folder).icon" />
                </svg>
            </div>

            <!-- Nome da pasta -->
            <span
                class="flex-1 transition-colors duration-200"
                :class="{
                    'text-gray-900 font-semibold': isActive(folder),
                    'text-gray-600 group-hover:text-gray-900': !isActive(folder)
                }"
            >
                {{ getConfig(folder).name }}
            </span>

            <!-- Badge de não lidos -->
            <span
                v-if="folder.unseen > 0"
                class="ml-2 px-2 py-0.5 text-xs font-bold rounded-full transition-all duration-200"
                :style="{
                    backgroundColor: isActive(folder) ? getConfig(folder).color : getConfig(folder).color + '20',
                    color: isActive(folder) ? 'white' : getConfig(folder).color
                }"
            >
                {{ folder.unseen }}
            </span>
        </Link>
    </nav>
</template>
