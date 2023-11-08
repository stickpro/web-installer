<template>
  <div id="installer-container" class="container grid-xl">
    <div id="installer">
      <Sidebar/>
      <div class="content">
        <transition-group name="fade">
          <Introduction
              v-show="isStepActive('intro')"
              :key="'intro'"
          />
          <Checks
              v-show="isStepActive('checks')"
              :key="'checks'"
              :installation="installation"
              @installPath="setInstallPath"
          />
          <License
              v-show="isStepActive('license')"
              :key="'license'"
          />
          <Configuration
              v-show="isStepActive('config')"
              :key="'config'"
              :site="site"
              :installation="installation"
          />
          <FinalChecks
              v-show="isStepActive('finalChecks')"
              :key="'finalChecks'"
              :site="site"
          />
          <Installation
              v-show="isStepActive('installation')"
              :key="'installation'"
              :site="site"
              :installation="installation"
              @installing="installation.installing = true"
          />
          <Complete
              v-show="isStepActive('complete')"
              :key="'complete'"
              :site="site"
          />
        </transition-group>
      </div>
    </div>
  </div>
</template>

<script>
import Sidebar from '@/components/Sidebar.vue';
import Introduction from '@/components/steps/Introduction.vue';
import Configuration from '@/components/steps/Configuration.vue';
import Checks from "@/components/steps/Checks.vue"
import License from "@/components/steps/License.vue";
import FinalChecks from "@/components/steps/FinalChecks.vue";
import Installation from "@/components/steps/Installation.vue";
import Complete from "@/components/steps/Complete.vue";
import {useStepsStore} from "@/stores/steps";
import {onMounted, ref} from "vue";

export default {
  name: 'Installer',
  components: {
    Sidebar,
    Introduction,
    Checks,
    License,
    Configuration,
    FinalChecks,
    Installation,
    Complete
  },
  setup() {
    const store = useStepsStore();

    const installation = ref({
      installing: false,
      installPath: null,
    })
    const site = ref({
      name: '',
      url: null,
      processingUrl: 'http://127.0.0.1:8082',
      backendUrl: 'api',
      database: {
        type: 'mysql',
        host: 'localhost',
        port: 3306,
        username: '',
        password: '',
        name: '',
      },
      admin: {
        firstName: '',
        lastName: '',
        email: '',
        username: '',
        password: '',
      },
    })

    const defaultUrl = () => {
      return window.location.protocol
          + '//'
          + window.location.host
          + (window.location.pathname.replace('/install.html', ''));
    }

    const setInstallPath = (path) => {
      installation.value.installPath = path
    }
    const isStepActive = (id) => {
      return store.isActive(id);
    };

    onMounted(() => {
      store.goTo('intro')
      site.value.url = defaultUrl()
    });

    return {
      isStepActive,
      installation,
      setInstallPath,
      site
    };
  }
}
;
</script>

<style lang="scss">
@import "@/assets/scss/base";

html,
body {
  height: 100%;
}

body {
  background-size: cover;
  background-repeat: no-repeat;
  background-position: center;
}

#installer-container {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;

  #installer {
    display: flex;
    flex-direction: row;
    width: 100%;
    height: 90%;
    max-height: 600px;

    background: $body-bg;
    border-radius: $border-radius;
    box-shadow: 0px 12px 6px rgba(0, 0, 0, 0.18);

    .content {
      position: relative;
      height: 100%;
      width: 100%;
    }
  }
}
</style>
