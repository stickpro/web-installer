<template>
  <div class="step">
    <div class="step-content">
      <h4>We're now running a couple of checks.</h4>
      <p>To ensure that DV PAY can install and run on your web server, we're just doing
        a couple of checks of your PHP and server configuration.</p>
    </div>
    <div class="checks">
      <Check
          name="PHP Version"
          :description="checkDescription('phpVersion')"
          :status="checkStatus('phpVersion')"
      />
      <Check
          name="PHP Extensions"
          :description="checkDescription('phpExtensions')"
          :status="checkStatus('phpExtensions')"
      />
    </div>
    <div class="step-actions">
      <Click
          :label="buttonLabel"
          :flag="(completedChecks && !checksSuccessful) ? 'error' : 'primary'"
          :disabled="!completedChecks"
          @press="onPress"
      />
    </div>
  </div>
</template>
<script>
import {useStep} from "@/composables/useStep";
import Check from "@/components/Check.vue";
import Click from "@/components/Click.vue";
import {useStepsStore} from "@/stores/steps";
import {computed, defineProps, getCurrentInstance, onMounted, provide, ref, watch} from "vue";

export default {
  name: "Checks",
  components: {
    Check,
    Click
  },
  setup() {
    const {isActive} = useStep('checks', 'System Checks');
    const store = useStepsStore();

    const props = defineProps({
      installation: {
        type: Object,
        required: true,
      },
    });

    // Data
    const ranChecks = ref(false);
    const completedChecks = ref(false);
    const checksSuccessful = ref(false);
    const checks = ref({});
    const secureError = ref(false);
    const disablingSSLChecks = ref(false);

    const {proxy} = getCurrentInstance();

    // Computed properties
    const buttonLabel = computed(() => {
      if (!completedChecks.value) {
        return 'Running checks...';
      }
      if (!checksSuccessful.value) {
        return 'Re-run checks';
      }
      return 'Continue installation';
    });

    // Methods
    const checkDescription = (check) => {
      if (!checks.value[check]) {
        return '';
      }
      return checks.value[check].description;
    };
    const runChecks = () => {
      ranChecks.value = true

      Promise.all([
        proxy.$api('GET', 'checkPhpVersion'),
        proxy.$api('GET', 'checkPhpExtensions'),
      ]).then(
          (responses) => {
            completedChecks.value = true
            checksSuccessful.value = responses.every((response) => response.success === true);

            checks.value.phpVersion.status = responses[0].success
            checks.value.phpExtensions.status = responses[1].success

            if (responses[0].success) {
              checks.value.phpVersion.description = `You are running PHP version ${responses[0].data.detected}, which is compatible with DV PAY.`;
            } else {
              checks.value.phpVersion.description = `You are running PHP version ${responses[0].data.detected}, which is incompatible with DV PAY.`;
            }

            if (responses[1].success) {
              checks.value.phpExtensions.description = 'All the necessary PHP extensions required to run DV PAY are installed on your server.';
            } else {
              checks.value.phpExtensions.description = `You are missing the "${responses[1].data.extension}", which is required in order to run DV PAY. Please install it on your server and re-run the tests.`;
            }
          }
      )
    };

    const onPress = () => {
      if (checksSuccessful.value) {
        complete();
      } else {
        rerunChecks();
      }
    };

    const rerunChecks = () => {
      ranChecks.value = false;
      completedChecks.value = false;
      checksSuccessful.value = false;
      secureError.value = false;
      disablingSSLChecks.value = false;

      resetChecks()
      runChecks()
    };

    const resetChecks = () => {
      checks.value = {
        phpVersion: {
          status: null,
          description: 'Check that your server is running a compatible PHP version (PHP 7.2 to 8.0 supported).',
        },
        phpExtensions: {
          status: null,
          description: 'Check that all necessary PHP extensions are installed on your server.',
        },
      }
    };

    const complete = () => {
      store.setStatus({
        id: 'checks',
        status: 'complete',
      })
      store.goTo('license')
    };

    // Lifecycle hooks
    onMounted(() => {
      resetChecks();
      watch(isActive, (val) => {
        if (val && !ranChecks.value) {
          runChecks();
        }
      });
    });
    const checkStatus = (check) => {
      if (!checks.value[check] || checks.value[check].status === null) {
        return 'loading';
      }
      if (checks.value[check].status === false) {
        return 'error';
      }
      return 'success';
    };

    provide('installPath', ref(null));

    return {
      ranChecks,
      completedChecks,
      checksSuccessful,
      checks,
      secureError,
      disablingSSLChecks,
      buttonLabel,
      isActive,
      checkDescription,
      checkStatus,
      onPress,
    };
  }
}
</script>

<style lang="scss" scoped>
p {
  margin-bottom: 0;
}

.checks {
  display: flex;
  flex-direction: row;
  flex-grow: 20;
  flex-shrink: 1;
  margin: 0 $layout-spacing-lg;
}
</style>
