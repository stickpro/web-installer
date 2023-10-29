import {computed, inject, onBeforeUnmount, onMounted} from 'vue';
import { useStepsStore } from '@/stores/steps'
export function useStep(stepId, stepName) {
    const store = useStepsStore();


    const isActive = computed(() => {
        return store.isActive(stepId);
    });

    onMounted(() => {
        store.add( {
            id: stepId,
            name: stepName,
        });
    });

    onBeforeUnmount(() => {
        store.remove({
            id: stepId,
        });
    });

    return {
        isActive,
    };
}
