<template>
  <div id="app">
    <h4>responsive hl-form</h4>
    <hl-form :model="form1" ref="form1">
      <el-form-item label="HL Form label" prop="input1" required>
        <el-input v-model="form1.input1"></el-input>
      </el-form-item>
      <el-button @click="submit1">Submit</el-button>
    </hl-form>

    <el-divider></el-divider>

    <el-form :model="form2" ref="form2">
      <el-form-item label="EL Form label" prop="input1" required>
        <el-input v-model="form2.input1"></el-input>
      </el-form-item>
      <el-button @click="submit2">Submit</el-button>
    </el-form>
  </div>
</template>

<script>
import HLForm from "./Form";
export default {
  name: "App",
  components: {
    "hl-form": HLForm,
  },
  data() {
    return {
      form1: {},
      form2: {},
    };
  },
  methods: {
    submit1() {
      this.$refs.form1.validate();
    },
    submit2() {
      this.$refs.form2.validate();
    },
  },
};
</script>
