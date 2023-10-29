<template>
  <div class="step text-center">
    <div class="step-content">
      <div class="circle-logo">
        <img src="@/assets/img/logo.svg" alt="DV PAY">
      </div>

      <h3>Welcome to the DV PAY Installer.</h3>

      <p>
        Thank you for choosing DV PAY as your payment processing platform. Get ready to enjoy your work.
        again!
      </p>
      <p>
        This installer will guide you through the process of installing a fresh copy of DV PAY
        for your project. Please follow the instructions provided and fill in all necessary
        information.
      </p>
    </div>

    <div class="step-actions">
      <Click
          label="Begin compatibility checks"
          flag="primary"
          @press="complete"
      />
    </div>
  </div>
</template>
<script>
import {useStep} from '@/composables/useStep';
import Click from '@/components/Click.vue';
import {useStepsStore} from "@/stores/steps";

export default {
  name: 'Introduction',
  components: {
    Click
  },
  setup() {
    const {isActive} = useStep('intro', 'Introduction');
    const store = useStepsStore();

    const complete = () => {
      store.setStatus({
        id: 'intro',
        status: 'complete',
      })
      store.goTo('checks')
    };

    return {
      isActive,
      complete,
    };
  },
};
</script>


<style lang="scss" scoped>
.step-content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;

  text-align: center;

  .circle-logo {
    width: 120px;
    margin-bottom: $layout-spacing;
  }
}

p {
  margin-bottom: 0.6rem;
}
</style>
