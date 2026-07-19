import { ref } from 'vue';

/**
 * Composable for handling flash message toasts.
 *
 * Usage:
 * ```vue
 * <script setup>
 * import { useFlashToast } from '@/composables/useFlashToast';
 *
 * const { showErrorToast, showSuccessToast, errorMessage, successMessage, showFlashMessages, ToastComponents } = useFlashToast();
 *
 * // In your router calls:
 * router.post(url, data, {
 *     onSuccess: (page) => {
 *         showFlashMessages(page.props);
 *     },
 * });
 * </script>
 *
 * <template>
 *     <!-- Add at end of template -->
 *     <Toast
 *         :show="showErrorToast"
 *         :message="errorMessage"
 *         variant="error"
 *         position="top-right"
 *         @close="showErrorToast = false"
 *     />
 *     <Toast
 *         :show="showSuccessToast"
 *         :message="successMessage"
 *         variant="success"
 *         position="top-right"
 *         @close="showSuccessToast = false"
 *     />
 * </template>
 * ```
 */
export function useFlashToast() {
    const showErrorToast = ref(false);
    const showSuccessToast = ref(false);
    const errorMessage = ref('');
    const successMessage = ref('');

    /**
     * Show flash messages from Inertia page props.
     * Handles repeated messages by resetting state first.
     */
    const showFlashMessages = (pageProps) => {
        if (pageProps?.flash?.error) {
            errorMessage.value = pageProps.flash.error;
            showErrorToast.value = false;
            setTimeout(() => showErrorToast.value = true, 0);
        }
        if (pageProps?.flash?.success) {
            successMessage.value = pageProps.flash.success;
            showSuccessToast.value = false;
            setTimeout(() => showSuccessToast.value = true, 0);
        }
    };

    /**
     * Manually show an error toast.
     */
    const showError = (message) => {
        errorMessage.value = message;
        showErrorToast.value = false;
        setTimeout(() => showErrorToast.value = true, 0);
    };

    /**
     * Manually show a success toast.
     */
    const showSuccess = (message) => {
        successMessage.value = message;
        showSuccessToast.value = false;
        setTimeout(() => showSuccessToast.value = true, 0);
    };

    /**
     * Hide all toasts.
     */
    const hideAll = () => {
        showErrorToast.value = false;
        showSuccessToast.value = false;
    };

    return {
        showErrorToast,
        showSuccessToast,
        errorMessage,
        successMessage,
        showFlashMessages,
        showError,
        showSuccess,
        hideAll,
    };
}
