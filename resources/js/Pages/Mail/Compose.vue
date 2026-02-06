<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import FolderList from '@/Components/FolderList.vue';
import TipTapEditor from '@/Components/TipTapEditor.vue';
import RecipientInput from '@/Components/RecipientInput.vue';
import AttachmentUploader from '@/Components/AttachmentUploader.vue';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    user: Object,
    branding: Object,
    folders: {
        type: Array,
        default: () => [],
    },
    mode: {
        type: String,
        default: 'new',
    },
    original: Object,
    signature: {
        type: String,
        default: '',
    },
});

const primaryColor = props.branding?.colors?.primary || '#3B82F6';

// Estado do formulário
const toRecipients = ref([]);
const ccRecipients = ref([]);
const bccRecipients = ref([]);
const subject = ref('');
const bodyHtml = ref('');
const quotedHtml = ref('');
const attachments = ref([]);
const showCc = ref(false);
const showBcc = ref(false);
const isSending = ref(false);
const isSavingDraft = ref(false);
const sendError = ref('');
const sendSuccess = ref('');
const contacts = ref([]);
const quoteIframeRef = ref(null);
const quoteIframeHeight = ref(200);

// Carrega contatos para autocomplete
const loadContacts = async () => {
    try {
        const response = await fetch('/api/mail/contacts/suggest', {
            headers: { 'Accept': 'application/json' },
        });
        if (response.ok) {
            contacts.value = await response.json();
        }
    } catch (e) {
        // Silencioso - autocomplete é opcional
    }
};

// Pré-preenche baseado no modo (reply/forward)
const prefill = () => {
    if (!props.original) return;

    const orig = props.original;

    if (props.mode === 'reply') {
        // Reply-To tem prioridade, senão usa From
        const replyAddr = orig.reply_to?.length ? orig.reply_to : orig.from;
        toRecipients.value = (replyAddr || []).map(a => ({ email: a.email, name: a.name || '' }));
        subject.value = orig.subject?.startsWith('Re:') ? orig.subject : `Re: ${orig.subject || ''}`;
        buildQuotedBody(orig);
    } else if (props.mode === 'reply_all') {
        const replyAddr = orig.reply_to?.length ? orig.reply_to : orig.from;
        toRecipients.value = (replyAddr || []).map(a => ({ email: a.email, name: a.name || '' }));

        // CC = todos os To + CC originais, excluindo o próprio usuário
        const userEmail = props.user?.email?.toLowerCase();
        const allCc = [...(orig.to || []), ...(orig.cc || [])]
            .filter(a => a.email?.toLowerCase() !== userEmail)
            .filter(a => !toRecipients.value.some(t => t.email?.toLowerCase() === a.email?.toLowerCase()));

        if (allCc.length) {
            ccRecipients.value = allCc.map(a => ({ email: a.email, name: a.name || '' }));
            showCc.value = true;
        }

        subject.value = orig.subject?.startsWith('Re:') ? orig.subject : `Re: ${orig.subject || ''}`;
        buildQuotedBody(orig);
    } else if (props.mode === 'forward') {
        subject.value = orig.subject?.startsWith('Fwd:') ? orig.subject : `Fwd: ${orig.subject || ''}`;
        buildForwardBody(orig);
    }
};

const buildQuotedBody = (orig) => {
    const sig = props.signature ? `<div class="signature">${props.signature}</div>` : '';
    // Corpo editável: só assinatura (texto novo vai antes)
    bodyHtml.value = sig ? `<br><br>${sig}` : '';

    // Citação: renderizada fora do TipTap para preservar HTML original
    const fromStr = orig.from?.map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ') || '';
    const dateStr = orig.date_formatted || '';
    const originalBody = orig.body_html || `<pre style="white-space: pre-wrap; font-family: sans-serif;">${orig.body_text || ''}</pre>`;

    quotedHtml.value = `<div style="border-left: 2px solid #ccc; padding-left: 12px; margin-left: 0; color: #555;"><p style="margin: 0 0 8px 0;">Em ${dateStr}, ${fromStr} escreveu:</p>${originalBody}</div>`;
};

const buildForwardBody = (orig) => {
    const sig = props.signature ? `<div class="signature">${props.signature}</div>` : '';
    bodyHtml.value = sig ? `<br><br>${sig}` : '';

    const fromStr = orig.from?.map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ') || '';
    const toStr = orig.to?.map(a => a.name ? `${a.name} <${a.email}>` : a.email).join(', ') || '';
    const dateStr = orig.date_formatted || '';
    const originalBody = orig.body_html || `<pre style="white-space: pre-wrap; font-family: sans-serif;">${orig.body_text || ''}</pre>`;

    quotedHtml.value = `<div style="border-top: 1px solid #ccc; padding-top: 12px; color: #555;"><p style="margin: 0 0 8px 0;"><strong>---------- Mensagem encaminhada ----------</strong><br>De: ${fromStr}<br>Data: ${dateStr}<br>Assunto: ${orig.subject || ''}<br>Para: ${toStr}</p>${originalBody}</div>`;
};

const loadQuoteIframe = () => {
    if (!quoteIframeRef.value || !quotedHtml.value) return;

    const iframe = quoteIframeRef.value;
    const doc = iframe.contentDocument || iframe.contentWindow.document;

    const htmlContent = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { margin: 0; padding: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.5; color: #374151; background: #f9fafb; }
img { max-width: 100%; height: auto; }
table { border-collapse: collapse; max-width: 100%; }
a { color: ${primaryColor}; }
pre { white-space: pre-wrap; word-break: break-word; }
</style></head><body>${quotedHtml.value}</body></html>`;

    doc.open();
    doc.write(htmlContent);
    doc.close();

    // Ajusta altura do iframe
    const adjustHeight = () => {
        try {
            const height = doc.body.scrollHeight;
            quoteIframeHeight.value = Math.max(height + 16, 100);
        } catch (e) {
            quoteIframeHeight.value = 300;
        }
    };

    setTimeout(adjustHeight, 100);
    setTimeout(adjustHeight, 500);
};

onMounted(() => {
    loadContacts();
    prefill();

    // Se é email novo e tem assinatura, insere
    if (props.mode === 'new' && props.signature) {
        bodyHtml.value = `<br><br><div class="signature">${props.signature}</div>`;
    }

    // Carrega citação no iframe após o DOM renderizar
    if (quotedHtml.value) {
        setTimeout(loadQuoteIframe, 100);
    }
});

const logout = () => {
    router.post('/logout');
};

const getModeTitle = () => {
    const titles = {
        'new': 'Nova mensagem',
        'reply': 'Responder',
        'reply_all': 'Responder a todos',
        'forward': 'Encaminhar',
    };
    return titles[props.mode] || 'Nova mensagem';
};

const hasContent = computed(() => {
    return toRecipients.value.length > 0 || subject.value || bodyHtml.value.replace(/<[^>]*>/g, '').trim();
});

const getFullBody = () => {
    if (quotedHtml.value) {
        return bodyHtml.value + quotedHtml.value;
    }
    return bodyHtml.value;
};

const sendEmail = async () => {
    if (isSending.value) return;
    if (toRecipients.value.length === 0) {
        sendError.value = 'Adicione pelo menos um destinatário.';
        return;
    }

    isSending.value = true;
    sendError.value = '';

    try {
        const response = await fetch('/api/mail/send', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                to: toRecipients.value,
                cc: ccRecipients.value,
                bcc: bccRecipients.value,
                subject: subject.value,
                body_html: getFullBody(),
                attachments: attachments.value.map(a => ({ id: a.id, name: a.name })),
                in_reply_to: props.original?.message_id || null,
                references: props.original?.message_id || null,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            sendSuccess.value = 'E-mail enviado com sucesso!';
            setTimeout(() => {
                router.visit('/mail/inbox');
            }, 1000);
        } else {
            sendError.value = data.error || 'Falha ao enviar e-mail.';
        }
    } catch (e) {
        sendError.value = 'Erro de conexão ao enviar e-mail.';
    } finally {
        isSending.value = false;
    }
};

const saveDraft = async () => {
    if (isSavingDraft.value) return;

    isSavingDraft.value = true;
    sendError.value = '';

    try {
        const response = await fetch('/api/mail/drafts', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                to: toRecipients.value,
                cc: ccRecipients.value,
                bcc: bccRecipients.value,
                subject: subject.value,
                body_html: getFullBody(),
            }),
        });

        const data = await response.json();

        if (response.ok) {
            sendSuccess.value = 'Rascunho salvo.';
            setTimeout(() => { sendSuccess.value = ''; }, 3000);
        } else {
            sendError.value = data.error || 'Falha ao salvar rascunho.';
        }
    } catch (e) {
        sendError.value = 'Erro de conexão ao salvar rascunho.';
    } finally {
        isSavingDraft.value = false;
    }
};

const discard = () => {
    if (hasContent.value) {
        if (!confirm('Descartar esta mensagem?')) return;
    }
    router.visit('/mail/inbox');
};
</script>

<template>
    <Head :title="getModeTitle()" />

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
                    <Link
                        href="/mail/compose"
                        class="w-full py-2 text-white rounded-xl text-sm font-semibold shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] flex items-center justify-center space-x-2"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Escrever</span>
                    </Link>
                </div>

                <!-- Folder List Card -->
                <div class="flex-1 bg-white rounded-2xl shadow-lg">
                    <div class="flex-1 overflow-y-auto py-3">
                        <FolderList
                            :folders="folders"
                            current-folder=""
                            :primary-color="primaryColor"
                        />
                    </div>
                </div>
            </div>

            <!-- Compose Column -->
            <div class="flex-1 flex flex-col gap-4 overflow-hidden">
                <!-- Toolbar Card -->
                <div class="bg-white rounded-2xl shadow-lg px-6 py-3 flex items-center space-x-2 flex-shrink-0">
                    <Link
                        href="/mail/inbox"
                        class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-200 hover:scale-105"
                        title="Voltar"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>

                    <div class="h-6 w-px bg-gray-200"></div>

                    <h2 class="text-base font-semibold text-gray-800 flex-1">
                        {{ getModeTitle() }}
                    </h2>

                    <div class="flex items-center space-x-2">
                        <!-- Salvar rascunho -->
                        <button
                            @click="saveDraft"
                            :disabled="isSavingDraft"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-xl hover:bg-gray-50 transition-colors disabled:opacity-50"
                        >
                            {{ isSavingDraft ? 'Salvando...' : 'Salvar rascunho' }}
                        </button>

                        <!-- Descartar -->
                        <button
                            @click="discard"
                            class="p-2 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200"
                            title="Descartar"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>

                        <!-- Enviar -->
                        <button
                            @click="sendEmail"
                            :disabled="isSending || toRecipients.length === 0"
                            class="px-5 py-2 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:hover:scale-100 flex items-center space-x-2"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span>{{ isSending ? 'Enviando...' : 'Enviar' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Compose Form Card -->
                <main class="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl shadow-lg">
                    <!-- Alertas -->
                    <div v-if="sendError" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center shadow-sm">
                        <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ sendError }}
                    </div>
                    <div v-if="sendSuccess" class="mx-6 mt-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm flex items-center shadow-sm">
                        <svg class="h-5 w-5 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ sendSuccess }}
                    </div>

                    <!-- Campos do formulário -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-3">
                        <!-- Para -->
                        <RecipientInput
                            v-model="toRecipients"
                            :contacts="contacts"
                            label="Para:"
                            placeholder="Adicionar destinatário..."
                            :primary-color="primaryColor"
                        />

                        <!-- CC/BCC toggles -->
                        <div v-if="!showCc || !showBcc" class="flex items-center space-x-3 pl-12">
                            <button
                                v-if="!showCc"
                                type="button"
                                @click="showCc = true"
                                class="text-sm hover:underline"
                                :style="{ color: primaryColor }"
                            >Cc</button>
                            <button
                                v-if="!showBcc"
                                type="button"
                                @click="showBcc = true"
                                class="text-sm hover:underline"
                                :style="{ color: primaryColor }"
                            >Bcc</button>
                        </div>

                        <!-- CC -->
                        <RecipientInput
                            v-if="showCc"
                            v-model="ccRecipients"
                            :contacts="contacts"
                            label="Cc:"
                            placeholder="Adicionar Cc..."
                            :primary-color="primaryColor"
                        />

                        <!-- BCC -->
                        <RecipientInput
                            v-if="showBcc"
                            v-model="bccRecipients"
                            :contacts="contacts"
                            label="Bcc:"
                            placeholder="Adicionar Bcc..."
                            :primary-color="primaryColor"
                        />

                        <!-- Assunto -->
                        <div>
                            <input
                                v-model="subject"
                                type="text"
                                placeholder="Assunto"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:border-transparent transition-shadow"
                                :style="{ '--tw-ring-color': primaryColor + '40' }"
                            />
                        </div>

                        <!-- Editor -->
                        <TipTapEditor
                            v-model="bodyHtml"
                            placeholder="Escreva sua mensagem..."
                            min-height="300px"
                            :primary-color="primaryColor"
                        />

                        <!-- Citação da mensagem original (fora do TipTap para preservar HTML) -->
                        <div v-if="quotedHtml" class="mt-2 border-t border-gray-200 pt-3">
                            <iframe
                                ref="quoteIframeRef"
                                class="w-full border-0 rounded-lg bg-gray-50"
                                :style="{ height: quoteIframeHeight + 'px' }"
                                sandbox="allow-same-origin"
                            ></iframe>
                        </div>

                        <!-- Anexos -->
                        <AttachmentUploader
                            v-model:attachments="attachments"
                            :primary-color="primaryColor"
                        />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
