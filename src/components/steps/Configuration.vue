<template>
  <Form ref="form">
    <div class="step">
      <div class="step-content">
        <Tabs ref="configTabs" v-model="tabIndex">
          <Tab title="Site">
            <div class="columns">
              <div class="column">
                <Field
                    name="Site URL"
                    mode="eager"
                    rules="backendUrl"
                    :immediate="false"
                    v-slot="{ dirty, invalid, errors }"
                    slim
                >
                  <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                    <label class="form-label label-required" for="siteUrl">Site URL</label>
                    <small class="help">
                      Please provide a publicly-available address to your site. Make sure to include
                      <strong>https://</strong> or <strong>http://</strong> at the beginning of your URL.
                    </small>
                    <input
                        type="text"
                        class="form-input"
                        id="siteUrl"
                        name="siteUrl"
                        placeholder="Enter your site URL"
                        tabindex="2"
                        v-model="site.url"
                    >
                    <transition name="fade">
                      <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                      </div>
                    </transition>
                  </div>
                </Field>

                <Field
                    name="Backend Path"
                    mode="eager"
                    rules="backendUrl"
                    :immediate="false"
                    v-model="site.backendUrl"
                    v-slot="{ dirty, invalid, errors }"
                    slim
                >
                  <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                    <label class="form-label label-required" for="backendUrl">Backend Path</label>
                    <small class="help">
                      Provide the path that will be used to access the Backend. By default,
                      this is <strong>api</strong> (i.e. Backend would be accessible at https://example.com/api).
                    </small>
                    <input
                        type="text"
                        class="form-input"
                        id="backendUrl"
                        name="backendUrl"
                        v-model="site.backendUrl"
                        placeholder="Enter your backend path"
                        tabindex="2"
                    >
                    <transition name="fade">
                      <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                      </div>
                    </transition>
                  </div>
                </Field>
              </div>
            </div>
          </Tab>
          <Tab title="Database">
            <div class="columns">
              <div class="column">
                <div class="form-group">
                  <label class="form-label" for="databaseType">Database Type</label>
                  <select
                      class="form-select"
                      id="databaseType"
                      name="databaseType"
                      tabindex="4"
                  >
                    <option value="mysql">MySQL / MariaDB</option>
                  </select>
                </div>
              </div>
              <div class="column"></div>
            </div>
            <div class="columns">
              <div class="column">
                <Field
                    name="Server Hostname"
                    mode="eager"
                    rules="required"
                    :immediate="false"
                    v-slot="{ dirty, invalid, errors }"
                    slim
                >
                  <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                    <label class="form-label label-required" for="databaseHost">
                      Server Hostname
                    </label>
                    <input
                        type="text"
                        class="form-input"
                        id="databaseHost"
                        name="databaseHost"
                        placeholder="Enter the database server hostname"
                        v-model="site.database.host"
                        tabindex="2"
                    >
                    <transition name="fade">
                      <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                      </div>
                    </transition>
                  </div>
                </Field>
                <div class="form-group">
                  <label class="form-label" for="databaseUser">Username</label>
                  <input
                      type="text"
                      class="form-input"
                      id="databaseUser"
                      name="databaseUser"
                      v-model="site.database.user"
                      tabindex="7"
                  >
                </div>
                <!--                <Field-->
                <!--                    name="Database Name"-->
                <!--                    mode="eager"-->
                <!--                    rules="required"-->
                <!--                    :immediate="false"-->
                <!--                    v-slot="{ dirty, invalid, errors }"-->
                <!--                    slim-->
                <!--                >-->
                <!--                  <div class="form-group" :class="{ 'has-error': dirty && invalid }">-->
                <!--                    <label class="form-label label-required" for="databaseName">-->
                <!--                      Database Name-->
                <!--                    </label>-->
                <!--                    <input-->
                <!--                        type="text"-->
                <!--                        class="form-input"-->
                <!--                        id="databaseName"-->
                <!--                        name="databaseName"-->
                <!--                        placeholder="Enter the database name"-->
                <!--                        v-model="site.database.name"-->
                <!--                        tabindex="2"-->
                <!--                    >-->
                <!--                    <transition name="fade">-->
                <!--                      <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">-->
                <!--                      </div>-->
                <!--                    </transition>-->
                <!--                  </div>-->
                <!--                </Field>-->
                <label class="form-label" for="databaseName">Database Name</label>
                <select
                    class="form-select"
                    id="databaseName"
                    name="databaseName"
                    tabindex="4"
                    v-model="site.database.name"
                >
                  <option v-for="database in databaseList" :value="database">{{ database }}</option>
                  <option v-if="site.database.user === 'root'" :value="null">Create New Database</option>
                </select>
              </div>
              <div class="column">
                <Field
                    name="Server Port"
                    mode="eager"
                    rules="required|integer"
                    :immediate="false"
                    v-slot="{ dirty, invalid, errors }"
                    slim
                >
                  <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                    <label class="form-label label-required" for="databasePort">Server Port</label>
                    <input
                        type="text"
                        class="form-input"
                        id="databasePort"
                        name="databasePort"
                        placeholder="Enter the database server port"
                        v-model="site.database.port"
                        tabindex="6"
                    >
                    <transition name="fade">
                      <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                      </div>
                    </transition>
                  </div>
                </Field>
                <div class="form-group">
                  <label class="form-label" for="databasePass">Password</label>
                  <div class="input-group">
                    <input
                        :type="(passwordVisible) ? 'text' : 'password'"
                        class="form-input"
                        id="databasePass"
                        name="databasePass"
                        v-model="site.database.pass"
                        tabindex="8"
                    >
                    <Click
                        @press="togglePasswordVisibility"
                        :label="(passwordVisible) ? 'Hide' : 'Show'"
                        addClasses="input-group-btn"
                        flag="primary"
                    />
                  </div>
                </div>
              </div>
            </div>
          </Tab>
          <Tab title="Administrator">
            <div class="columns">
              <div class="column">
                <div class="form-group">
                  <Field
                      name="Email Address"
                      mode="eager"
                      rules="required|email"
                      :immediate="false"
                      v-slot="{ dirty, invalid, errors }"
                      slim
                  >
                    <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                      <label class="form-label label-required" for="adminEmail">Email Address</label>
                      <input
                          type="text"
                          class="form-input"
                          id="adminEmail"
                          name="adminEmail"
                          placeholder="Enter the admin email address"
                          v-model="site.admin.email"
                          tabindex="14"
                      >
                      <transition name="fade">
                        <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                        </div>
                      </transition>
                    </div>
                  </Field>
                  <Field
                      name="Password"
                      mode="eager"
                      rules="required|min:4"
                      :immediate="false"
                      v-slot="{ dirty, invalid, errors }"
                      slim
                  >
                    <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                      <label class="form-label label-required" for="adminPassword">Password</label>
                      <div class="input-group">
                        <input
                            :type="(passwordVisible) ? 'text' : 'password'"
                            class="form-input"
                            id="adminPassword"
                            name="adminPassword"
                            placeholder="Enter the admin's password"
                            v-model="site.admin.password"
                            tabindex="13"
                        >
                        <Click
                            @press="togglePasswordVisibility"
                            :label="(passwordVisible) ? 'Hide' : 'Show'"
                            addClasses="input-group-btn"
                            flag="primary"
                        />
                      </div>
                      <transition name="fade">
                        <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                        </div>
                      </transition>
                    </div>
                  </Field>
                </div>

              </div>
            </div>
          </Tab>
          <Tab title="Processing">
            <div class="columns">
              <div class="column">
                <div class="form-group">
                  <Field
                      name="Site URL"
                      mode="eager"
                      rules="backendUrl"
                      :immediate="false"
                      v-slot="{ dirty, invalid, errors }"
                      slim
                  >
                    <div class="form-group" :class="{ 'has-error': dirty && invalid }">
                      <label class="form-label label-required" for="siteUrl">Processing URL</label>
                      <small class="help">
                        Please provide a publicly-available address to your processing. Make sure to include
                        <strong>https://</strong> or <strong>http://</strong> at the beginning of your URL.
                      </small>
                      <input
                          type="text"
                          class="form-input"
                          id="siteUrl"
                          name="siteUrl"
                          placeholder="Enter your site URL"
                          tabindex="2"
                          v-model="site.processingUrl"
                      >
                      <transition name="fade">
                        <div v-if="dirty && errors.length" class="form-error" v-text="errors[0]">
                        </div>
                      </transition>
                    </div>
                  </Field>

                </div>
              </div>
            </div>
          </Tab>
        </Tabs>
      </div>
      <div class="step-actions">
        <Click
            v-if="tabIndex === 0"
            label="Enter database configuration"
            flag="primary"
            @press="tabIndex = 1"
        />
        <Click
            v-else-if="tabIndex === 1"
            label="Enter administrator details"
            flag="primary"
            @press="tabIndex = 2"
        />
        <Click
            v-else-if="tabIndex === 2"
            label="Enter processing details"
            flag="primary"
            @press="tabIndex = 3"
        />
        <Click
            v-else
            label="Begin final checks"
            flag="primary"
            @press="complete()"
        />
      </div>
    </div>
  </Form>
</template>


<script>
import {useStep} from "@/composables/useStep";
import {getCurrentInstance, ref, watch} from "vue";
import {defineRule, Field, Form} from 'vee-validate'
import {required} from '@vee-validate/rules'
import Tabs from '@/components/Tabs.vue';
import Tab from '@/components/Tab.vue';
import Click from '@/components/Click.vue';
import {useStepsStore} from "@/stores/steps";

const backendUrl = (value) => {
  const regex = /^[-a-zA-Z0-9()@:%_+.~#=]*$/;
  if (regex.test(value)) {
    return true;
  }

  return 'Invalid backend URL keyword.';
}
const url = (value) => {
  const regex = /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}(\.[a-zA-Z0-9()]{1,6})*\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)$/;
  if (regex.test(value)) {
    return true;
  }
  return 'Invalid URL provided';
}
export default {
  name: "Configuration",
  components: {
    Form,
    Field,
    Tabs,
    Tab,
    Click,
  },
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
    defineRule('required', required);
    defineRule('backendUrl', backendUrl);
    defineRule('url', url);
    useStep('config', 'Configuration')
    const store = useStepsStore();

    // Data
    const site = ref(props.site);
    const form = ref(null)
    const tabIndex = ref(0);
    const passwordVisible = ref(false);
    const databaseList = ref([]);

    const {proxy} = getCurrentInstance();


    const togglePasswordVisibility = () => {
      passwordVisible.value = !passwordVisible.value
    }
    const complete = () => {
      store.setStatus({
        id: 'config',
        status: 'complete',
      })
      store.goTo('finalChecks')
    }
    // Methods
    watch(() => props.site, (newSite) => {
      site.value = newSite;
    });

    watch(() => props.site.database, (newDatabase, oldDatabase) => {
      if (props.site.database.user === 'root') {
        proxy.$api('POST', 'loadDatabase', {site: props.site}).then(
            (response) => {
              databaseList.value = response.data.databaseList
            }
        )
      }
    }, {deep: true});


    return {
      site,
      tabIndex,
      togglePasswordVisibility,
      passwordVisible,
      form,
      complete,
      databaseList
    }

  }
}
</script>


<style scoped lang="scss">

</style>
