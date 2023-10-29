import { defineStore } from 'pinia';
export const useStepsStore = defineStore('steps', {
    state: () => ({
        steps: [],
    }),
    actions: {
        add(data) {
          this.steps.push({
              id: data.id,
              name: data.name,
              status: 'locked',
              active: false,
          })
        },
        remove(data) {
            const step = this.getStepById(data.id)
            this.steps.splice(step.key, 1)
        },
        setStatus(data) {
            const key = this.getStepById(data.id)
            this.steps[key].status = data.status
        },
        goTo(id) {
            const step = this.steps.find((s) => s.id === id);
            if(step.id === 'intro' || step.status === 'complete') {
                this.setActive(id);
                return;
            }
            if (!step || this.steps.find((s) => s.id === id).status !== 'locked') {
                return;
            }
            this.setActive(id);
        },
        isLocked(id) {
            const step = this.steps.find((step) => step.id === id);
            return step ? step.status === 'locked' : false;
        },
        isActive(id) {
            const step = this.steps.find((step) => step.id === id);
            return step ? step.active : false;
        },
        getStepById(id) {
            return this.steps.findIndex((step) => step.id === id)
        },
        setActive(id) {
            this.steps.forEach((step) => {
                step.active = step.id === id;
            });
        },
    },
});
