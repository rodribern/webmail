<script setup>
import { ref } from 'vue';

const props = defineProps({
    attachments: {
        type: Array,
        default: () => [],
    },
    primaryColor: {
        type: String,
        default: '#3B82F6',
    },
});

const emit = defineEmits(['update:attachments']);

const fileInput = ref(null);
const isUploading = ref(false);
const uploadError = ref('');

const maxFileSize = 10 * 1024 * 1024; // 10MB por arquivo
const maxTotalSize = 25 * 1024 * 1024; // 25MB total

const currentTotalSize = () => {
    return props.attachments.reduce((sum, a) => sum + (a.size || 0), 0);
};

const formatSize = (bytes) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const triggerUpload = () => {
    fileInput.value?.click();
};

const handleFileSelect = async (event) => {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;

    uploadError.value = '';

    for (const file of files) {
        if (file.size > maxFileSize) {
            uploadError.value = `"${file.name}" excede o limite de 10MB.`;
            continue;
        }

        if (currentTotalSize() + file.size > maxTotalSize) {
            uploadError.value = 'Limite total de 25MB atingido.';
            break;
        }

        await uploadFile(file);
    }

    // Limpa o input para permitir selecionar o mesmo arquivo novamente
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const uploadFile = async (file) => {
    isUploading.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('/api/mail/attachments', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: formData,
        });

        if (!response.ok) {
            const data = await response.json();
            uploadError.value = data.error || 'Falha ao enviar anexo.';
            return;
        }

        const data = await response.json();
        emit('update:attachments', [...props.attachments, data]);
    } catch (e) {
        uploadError.value = 'Erro de conexão ao enviar anexo.';
    } finally {
        isUploading.value = false;
    }
};

const removeAttachment = async (index) => {
    const attachment = props.attachments[index];
    if (!attachment) return;

    try {
        await fetch(`/api/mail/attachments/${attachment.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        });
    } catch (e) {
        // Ignora erro na remoção do servidor
    }

    const updated = [...props.attachments];
    updated.splice(index, 1);
    emit('update:attachments', updated);
};

const getFileIcon = (mime) => {
    if (!mime) return 'file';
    if (mime.startsWith('image/')) return 'image';
    if (mime.includes('pdf')) return 'pdf';
    if (mime.includes('word') || mime.includes('document')) return 'doc';
    if (mime.includes('spreadsheet') || mime.includes('excel')) return 'sheet';
    return 'file';
};
</script>

<template>
    <div>
        <input
            ref="fileInput"
            type="file"
            multiple
            class="hidden"
            @change="handleFileSelect"
        />

        <!-- Botão de upload -->
        <button
            type="button"
            @click="triggerUpload"
            :disabled="isUploading"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors disabled:opacity-50"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
            </svg>
            <span v-if="isUploading">Enviando...</span>
            <span v-else>Anexar arquivo</span>
        </button>

        <!-- Erro -->
        <div v-if="uploadError" class="mt-2 text-sm text-red-600">
            {{ uploadError }}
        </div>

        <!-- Lista de anexos -->
        <div v-if="attachments.length > 0" class="mt-3 space-y-2">
            <div
                v-for="(attachment, index) in attachments"
                :key="attachment.id"
                class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg group"
            >
                <div class="flex items-center space-x-3 min-w-0">
                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <div class="min-w-0">
                        <div class="text-sm text-gray-700 truncate">{{ attachment.name }}</div>
                        <div class="text-xs text-gray-400">{{ attachment.size_human || formatSize(attachment.size) }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    @click="removeAttachment(index)"
                    class="flex-shrink-0 p-1 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
                    title="Remover"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="text-xs text-gray-400">
                Total: {{ formatSize(currentTotalSize()) }} / 25 MB
            </div>
        </div>
    </div>
</template>
