import { ref, provide } from 'vue';

const tabCollection = () => {
    const tabs = ref([]);
    const activeTab = ref(null);
    let tabCount = 0;

    const addTab = (tab) => {
        tabs.value.push(tab);
        tabCount += 1;
        if (tab.isActive === true) {
            setActiveTab(tab);
        }
    };

    const findTab = (tab) => tabs.value.findIndex((item) => item === tab);

    const setActiveTab = (tab) => {
        const key = findTab(tab);
        if (key !== -1) {
            activeTab.value = key;
        }
        refreshTabActiveStates();
    };

    const setActiveTabByKey = (key, parent) => {
        activeTab.value = key;
        refreshTabActiveStates();
        parent.$emit('tabchanged', tabs.value[key]);
    };

    const removeTab = (tab) => {
        const key = findTab(tab);
        if (key !== -1) {
            tabs.value.splice(key, 1);
            tabCount -= 1;
        }
    };

    const refreshTabActiveStates = () => {
        tabs.value.forEach((tab, index) => {
            tab.isActive = false;
            if (activeTab.value === index) {
                tab.isActive = true;
            }
        });
    };

    return {
        tabs,
        activeTab,
        tabCount,
        addTab,
        findTab,
        setActiveTab,
        setActiveTabByKey,
        removeTab,
        refreshTabActiveStates,
    };
};

export const useTabCollection = () => {
    const collection = tabCollection();
    provide('$tabs', collection);
    return collection;
};
