<script setup>
import { Head, router } from '@inertiajs/vue3';
import FolderList from '@/Components/FolderList.vue';
import MessageList from '@/Components/MessageList.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    user: Object,
    branding: Object,
    folders: {
        type: Array,
        default: () => []
    },
    messages: {
        type: Array,
        default: () => []
    },
    pagination: {
        type: Object,
        default: () => ({ total: 0, page: 1, per_page: 50, total_pages: 1 })
    },
    currentFolder: {
        type: String,
        default: 'INBOX'
    },
    error: String
});

const primaryColor = props.branding?.colors?.primary || '#3B82F6';

const logout = () => {
    router.post('/logout');
};

const refresh = () => {
    router.reload();
};

const getFolderDisplayName = (path) => {
    const names = {
        'INBOX': 'Caixa de Entrada',
        'Sent': 'Enviados',
        'Drafts': 'Rascunhos',
        'Trash': 'Lixeira',
        'Spam': 'Spam',
        'Junk': 'Lixo Eletrônico',
    };
    return names[path] || path;
};

const getCurrentUrl = () => {
    if (props.currentFolder === 'INBOX') {
        return '/mail/inbox';
    }
    return `/mail/folder/${encodeURIComponent(props.currentFolder)}`;
};
</script>

<template>
    <Head :title="getFolderDisplayName(currentFolder)" />

    <div class="min-h-screen flex flex-col bg-gray-100 p-4 gap-4">
        <!-- Header -->
        <header class="bg-white rounded-2xl shadow-lg flex-shrink-0 relative z-10">
            <div class="max-w-full mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <img
                        v-if="branding?.logo"
                        :src="branding.logo"
                        alt="Logo"
                        class="h-20 max-w-72 object-contain"
                    />
                    <h1 v-else class="text-xl font-bold" :style="{ color: primaryColor }">
                        Webmail
                    </h1>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Admin link -->
                    <a
                        v-if="user?.is_admin"
                        href="/admin/branding"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-200"
                        title="Personalização do domínio"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </a>

                    <!-- User info -->
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium shadow-sm"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ user?.email?.charAt(0).toUpperCase() || '?' }}
                        </div>
                        <span class="text-sm text-gray-600 hidden sm:block">{{ user?.email }}</span>
                    </div>
                    <button
                        @click="logout"
                        class="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-100 transition-colors duration-200"
                    >
                        Sair
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 flex overflow-hidden gap-4">
            <!-- Sidebar Column -->
            <div class="w-72 flex-shrink-0 flex flex-col gap-4">
                <!-- Compose Button Card -->
                <div class="bg-white rounded-2xl shadow-lg px-4 py-3">
                    <button
                        class="w-full py-2 text-white rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center space-x-2"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Escrever</span>
                    </button>
                </div>

                <!-- Folder List Card -->
                <div class="flex-1 bg-white rounded-2xl shadow-lg">
                    <div class="flex-1 overflow-y-auto py-3">
                        <FolderList
                            :folders="folders"
                            :current-folder="currentFolder"
                            :primary-color="primaryColor"
                        />
                    </div>
                </div>
            </div>

            <!-- Message Column -->
            <div class="flex-1 flex flex-col gap-4 overflow-hidden">
                <!-- Toolbar Card -->
                <div class="bg-white rounded-2xl shadow-lg px-6 py-3 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center space-x-3">
                        <button
                            @click="refresh"
                            class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200 hover:scale-105 active:scale-95"
                            title="Atualizar"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <h2 class="text-base font-semibold text-gray-800">
                            {{ getFolderDisplayName(currentFolder) }}
                        </h2>
                        <span v-if="pagination.total > 0" class="text-sm text-gray-400">
                            ({{ pagination.total }} {{ pagination.total === 1 ? 'mensagem' : 'mensagens' }})
                        </span>
                    </div>

                    <div class="w-10"></div>
                </div>

                <!-- Message List Card -->
                <main class="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl shadow-lg">

                <!-- Error Alert -->
                <div v-if="error" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center shadow-sm">
                    <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    {{ error }}
                </div>

                <!-- Message List -->
                <div class="flex-1 overflow-y-auto">
                    <MessageList
                        :messages="messages"
                        :current-folder="currentFolder"
                        :primary-color="primaryColor"
                    />
                </div>

                <!-- Pagination -->
                <Pagination
                    :pagination="pagination"
                    :base-url="getCurrentUrl()"
                    :primary-color="primaryColor"
                />
                </main>
            </div>
        </div>
    </div>
</template>
