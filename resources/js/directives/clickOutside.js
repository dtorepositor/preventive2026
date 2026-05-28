/**
 * Click-outside directive for Vue 3
 * Triggers a callback when clicking outside the element
 */
export const vClickOutside = {
    mounted(el, binding) {
        el.clickOutsideEvent = function(event) {
            // Check if click is outside the element
            if (!(el === event.target || el.contains(event.target))) {
                binding.value(event);
            }
        };
        // Add event listener on document
        document.addEventListener('click', el.clickOutsideEvent);
    },
    unmounted(el) {
        // Remove event listener
        document.removeEventListener('click', el.clickOutsideEvent);
    },
};
