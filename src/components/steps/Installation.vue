<template>
  <div class="step text-center">
    <div class="step-content">
      <transition name="fade" mode="out-in">
        <div class="circle-logo" v-if="!errored">
          <img src="@/assets/img/logo.svg" alt="DV PAY">

        </div>
        <div class="danger-logo" v-else></div>
      </transition>

      <h3>Installing DV PAY</h3>

      <p>This process may take a minute or two. Please do not close the window.</p>

      <div class="bar bar-lg" :class="{ 'bar-error': errored }">
        <div
            class="bar-item"
            role="progressbar"
            :style="{ width: progress }"
        ></div>
      </div>

      <transition name="fade" mode="out-in">
        <div class="install-steps" v-if="!errored">
          <transition-group name="install-step" tag="ul">
            <li
                v-for="action in actioned"
                :key="action"
                class="install-step-item"
                v-text="action"
            ></li>
          </transition-group>
        </div>
        <div class="error" v-else>
          <p>
            Sorry, but an error has occurred while trying to install DV PAY.
          </p>
          <p><strong v-text="error"></strong></p>
        </div>
      </transition>


    </div>
  </div>
</template>

<script>
import {computed, getCurrentInstance, onMounted, ref, watch} from "vue";
import {useStep} from "@/composables/useStep";
import complete from "@/components/steps/Complete.vue";
import {useStepsStore} from "@/stores/steps";

export default {
  name: "Installation",
  props: {
    site: {
      type: Object,
      required: true,
    },
    installation: {
      type: Object,
      required: true,
    },
  },
  setup(props) {
    const errored = ref(false);
    const error = ref(null);
    const begun = ref(false);
    const apiSteps = ref({
      downloadDvPay: 'Downloading DV PAY',
      extractDvPay: 'Extracting DV PAY',
      lockDependencies: 'Determining dependencies',
      installDependencies: 'Installing dependencies',
      setupConfig: 'Configuring site',
      runMigrations: 'Running database migrations',
      createAdmin: 'Create administrator account',
      updateFrontendPath: 'Update Frontend path url',
      cleanUp: 'Cleaning up',
    });
    const actioned = ref([]);
    const {proxy} = getCurrentInstance();
    const store = useStepsStore();
    const {isActive} = useStep('installation', 'Installation')
    const progress = computed(() => {
      return Math.ceil((actioned.value.length / Object.keys(apiSteps.value).length) * 100) + '%';
    })

    // METHODS
    const install = async () => {
      begun.value = true;

      for (const key of Object.keys(apiSteps.value)) {
        actioned.value.unshift(apiSteps[key]);
        try {
          await installStep(key)
        } catch (e) {
          setError(e)
          return;
        }
      }
      complete()
    }

    const complete = () => {
      store.setStatus({
        id: 'installation',
        status: 'complete',
      })
      store.goTo('complete')
    }

    const installStep = (endpoint) => {
      return new Promise((resolve, reject) => {
        proxy.$api('POST', endpoint, {site: props.site}).then(
            (response) => {
              if (response.success) {
                resolve();
              }
              reject(response.error);
            },
            (error) => {
              reject(error);
            }
        )
      })
    }

    const setError = (message) => {
      errored.value = true;
      error.value = message
    }

    onMounted(() => {
      watch(isActive, (val) => {
        if (val && !watch.value) {
          install();
        }
      });
    });

    return {
      errored,
      error,
      begun,
      progress,
      actioned
    }
  }
}
</script>
<style lang="scss" scoped>
.step-content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;

  h3 {
    margin-bottom: 0;
  }

  .bar {
    height: $unit-8;
  }

  .circle-logo {
    width: 120px;
    margin-bottom: $layout-spacing;
  }

  .danger-logo {
    position: relative;
    width: 120px;
    height: 120px;
    margin-bottom: $layout-spacing;

    border-radius: 50%;
    background-color: $error-color;
    overflow: hidden;

    &:after {
      content: '✗';
      position: absolute;
      top: 100%;
      left: 50%;
      width: 70px;
      height: 70px;
      margin-top: -35px;
      margin-left: -40px;
      animation: showTick 1s cubic-bezier(0, 1.5, 1, 1) 500ms both;

      color: $light-color;
      text-align: center;
      line-height: 70px;
      font-weight: 700;
      font-size: 6em;
    }
  }

  .install-steps {
    position: relative;
    height: 170px;
    width: 100%;
    overflow: hidden;

    ul {
      margin: 0;
      padding: 0;
      list-style: none;
    }

    &::after {
      content: '';
      position: absolute;
      left: 0;
      bottom: 0;
      height: 80%;
      width: 100%;
      background: linear-gradient(
              rgba(255, 255, 255, 0) 0%,
              rgba(255, 255, 255, 0.5) 15%,
              rgba(255, 255, 255, 1) 100%
      );
    }

    .install-step-item {
      display: block;
      text-align: center;
      font-size: $font-size-lg;
      margin-bottom: $unit-2;
      font-weight: bold;
    }
  }

  .error {
    background: lighten($error-color, 10%);
    color: $light-color;
    border-radius: $border-radius;

    width: 100%;
    padding: $layout-spacing-sm $layout-spacing;

    p:last-child {
      margin-bottom: 0;
    }
  }
}

p {
  margin-bottom: 0.6rem;
}
</style>

