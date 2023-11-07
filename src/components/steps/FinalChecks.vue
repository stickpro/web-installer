<template>
  <div class="step">
    <div class="step-content">
      <h4>Last checks.</h4>

      <p>To ensure that DV PAY can install and run with your configuration, we're doing some
        final checks.</p>
    </div>
    <div class="checks">
      <Check
          name="Database connection"
          :description="checkDescription('database')"
          :status="checkStatus('database')"
      />
      <Check
          name="Directory is writable"
          :description="checkDescription('writable')"
          :status="checkStatus('writable')"
      />
    </div>
    <div class="step-actions">
      <Click
          :label="buttonLabel"
          :size="(completedChecks && checksSuccessful) ? 'lg' : 'md'"
          :flag="(completedChecks && !checksSuccessful) ? 'error' : 'success'"
          :disabled="!completedChecks"
          @press="onPress"
      />
    </div>
  </div>
</template>
<script>
import Check from '@/components/Check.vue'
import Click from '@/components/Click.vue'
import {computed, getCurrentInstance, onMounted, ref, watch} from "vue";
import {useStep} from "@/composables/useStep";
import {useStepsStore} from "@/stores/steps";

export default {
  name: "FinalChecks",
  components: {
    Check,
    Click,
  },
  props: {
    site: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    // DATA
    const checks = ref({});
    const completedChecks = ref(false);
    const checksSuccessful = ref(false);
    const ranChecks = ref(false);
    const {isActive} = useStep('finalChecks', 'Final Checks');
    const {proxy} = getCurrentInstance();
    const store = useStepsStore();


    // METHODS
    const runChecks = () => {
      ranChecks.value = true

      Promise.all([
        proxy.$api('POST', 'checkDatabase', {site: props.site}),
        proxy.$api('GET', 'checkWriteAccess'),
      ]).then((responses) => {
        completedChecks.value = true;
        checksSuccessful.value = responses.every((response) => response.success === true)

        checks.value.database.status = responses[0].success;
        checks.value.writable.status = responses[1].success;

        if (responses[0].success) {
          checks.value.database.description = 'We were successfully able to connect to the database.';
        } else if (responses[0].data.dbNotEmpty) {
          checks.value.database.description = 'The database you are installing to is not empty. Please delete all tables within this database before proceeding.';
        } else {
          checks.value.database.description = 'We could not connect to the database. Please check your database settings.';
        }

        if (responses[1].success) {
          checks.value.writable.description = 'The folder you are installing into is writable.';
        } else {
          checks.value.writable.description = 'The folder you are installing into is not writable. Please check the permissions and ownership of the folder.';
        }
      })
    }

    const resetChecks = () => {
      ranChecks.value = false;
      completedChecks.value = false;
      checksSuccessful.value = false;

      checks.value = {
        database: {
          status: null,
          description: 'Check that we can connect to the database using the provided configuration.',
        },
        writable: {
          status: null,
          description: 'Check that your current project folder is writable.',
        },
      }
    }
    const checkDescription = (check) => {
      if (!checks.value[check]) {
        return '';
      }
      return checks.value[check].description;
    };
    const checkStatus = (check) => {
      if (!checks.value[check] || checks.value[check].status === null) {
        return 'loading';
      }
      if (checks.value[check].status === false) {
        return 'error';
      }
      return 'success';
    };

    const onPress = () => {
      // DELETE
      complete()
      return;
      if (checksSuccessful.value) {
        complete();
        return;
      }
      rerunChecks();
    }

    const complete = () => {
      store.setStatus({
        id: 'finalChecks',
        status: 'complete',
      })
      store.goTo('installation')
    }

    const rerunChecks = () => {
      ranChecks.value = false;
      completedChecks.value = false;
      checksSuccessful.value = false;

      resetChecks()
      runChecks()
    };

    const buttonLabel = computed(() => {
      if (!completedChecks.value) {
        return 'Running checks...';
      }
      if (!checksSuccessful.value) {
        return 'Re-run checks';
      }
      return 'Begin the installation';
    });

    // HOCKS
    onMounted(() => {
      resetChecks();
      watch(isActive, (val) => {
        if (val && !ranChecks.value) {
          runChecks();
        }
      });
    });

    return {
      checkStatus,
      checkDescription,
      isActive,
      buttonLabel,
      completedChecks,
      checksSuccessful,
      onPress
    }
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
