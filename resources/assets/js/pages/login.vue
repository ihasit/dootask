<template>
    <div class="page-login">
        <PageTitle :title="$L('登录')"/>
        <div class="login-body">
            <div class="login-logo"></div>
            <div class="login-box">
                <div class="login-title">Welcome Dootask</div>

                <div v-if="loginType=='reg'" class="login-subtitle">{{$L('输入您的信息以创建帐户。')}}</div>
                <div v-else class="login-subtitle">{{$L('输入您的凭证以访问您的帐户。')}}</div>

                <div class="login-input">
                    <Input v-if="$Electron && cacheServerUrl" :value="cacheServerUrl" prefix="ios-globe-outline" size="large" readonly clearable @on-clear="onServerUrlClear"/>

                    <Input v-model="email" prefix="ios-mail-outline" :placeholder="$L('输入您的电子邮件')" size="large" @on-enter="onLogin" @on-blur="onBlur" />
                    <Input v-model="password" prefix="ios-lock-outline" :placeholder="$L('输入您的密码')" type="password" size="large" @on-enter="onLogin" />

                    <Input v-if="loginType=='reg'" v-model="password2" prefix="ios-lock-outline" :placeholder="$L('输入确认密码')" type="password" size="large" @on-enter="onLogin" />
                    <Input v-if="loginType=='reg' && needInvite" v-model="invite" class="login-code" :placeholder="$L('请输入注册邀请码')" type="text" size="large" @on-enter="onLogin"><span slot="prepend">&nbsp;{{$L('邀请码')}}&nbsp;</span></Input>

                    <Input v-if="loginType=='login' && codeNeed" v-model="code" class="login-code" :placeholder="$L('输入图形验证码')" size="large" @on-enter="onLogin">
                        <Icon type="ios-checkmark-circle-outline" class="login-icon" slot="prepend"></Icon>
                        <div slot="append" class="login-code-end" @click="reCode"><img :src="codeUrl"/></div>
                    </Input>

                    <Button type="primary" :loading="loadIng > 0 || loginJump" size="large" long @click="onLogin">{{$L(loginText)}}</Button>

                    <div v-if="loginType=='reg'" class="login-switch">{{$L('已经有帐号？')}}<a href="javascript:void(0)" @click="loginType='login'">{{$L('登录帐号')}}</a></div>
                    <div v-else class="login-switch">{{$L('还没有帐号？')}}<a href="javascript:void(0)" @click="loginType='reg'">{{$L('注册帐号')}}</a></div>
                </div>
            </div>
            <div class="login-bottom">
                <Dropdown trigger="click" @on-click="setLanguage" transfer>
                    <div class="login-language">
                        {{currentLanguage}}
                        <i class="taskfont">&#xe689;</i>
                    </div>
                    <Dropdown-menu slot="list">
                        <Dropdown-item v-for="(item, key) in languageList" :key="key" :name="key" :selected="getLanguage() === key">{{item}}</Dropdown-item>
                    </Dropdown-menu>
                </Dropdown>
                <div class="login-forgot">{{$L('忘记密码了？')}}<a href="javascript:void(0)" @click="forgotPassword">{{$L('重置密码')}}</a></div>
            </div>
        </div>
        <div class="login-right-bottom">
            <Button v-if="$Electron" icon="ios-globe-outline" type="primary" @click="onServerUrlInput">{{$L('自定义服务器')}}</Button>
            <AppDown/>
        </div>
    </div>
</template>

<script>
import AppDown from "../components/AppDown";
import {mapState} from "vuex";

export default {
    components: {AppDown},
    data() {
        return {
            loadIng: 0,

            codeNeed: false,
            codeUrl: this.$store.state.method.apiUrl('users/login/codeimg'),

            loginType: 'login',
            loginJump: false,
            email: this.$store.state.method.getStorageString("cacheLoginEmail") || '',
            password: '',
            password2: '',
            code: '',
            invite: '',

            demoAccount: {},

            needInvite: false,
        }
    },
    mounted() {
        this.getDemoAccount();
        //
        if (!this.$Electron && this.cacheServerUrl) {
            this.onServerUrlClear();
        }
    },
    deactivated() {
        this.loginJump = false;
        this.password = "";
        this.password2 = "";
        this.code = "";
        this.invite = "";
    },
    computed: {
        ...mapState(['cacheServerUrl']),

        currentLanguage() {
            return this.languageList[this.languageType] || 'Language'
        },
        loginText() {
            let text = this.loginType == 'login' ? '登录' : '注册';
            if (this.loginJump) {
                text += "成功..."
            }
            return text
        }
    },
    watch: {
        loginType(val) {
            if (val == 'reg') {
                this.getNeedInvite();
            }
        }
    },
    methods: {
        getDemoAccount() {
            this.$store.dispatch("call", {
                url: 'system/demo',
            }).then(({data}) => {
                this.demoAccount = data;
                if (data.account) {
                    this.email = data.account;
                    this.password = data.password;
                }
            }).catch(() => {
                this.demoAccount = {};
            });
        },

        getNeedInvite() {
            this.$store.dispatch("call", {
                url: 'users/reg/needinvite',
            }).then(({data}) => {
                this.needInvite = !!data.need;
            }).catch(() => {
                this.needInvite = false;
            });
        },

        forgotPassword() {
            $A.modalWarning("请联系管理员！");
        },

        reCode() {
            this.codeUrl = this.$store.state.method.apiUrl('users/login/codeimg?_=' + Math.random())
        },

        onServerUrlInput() {
            $A.modalInput({
                title: "自定义服务器",
                value: this.cacheServerUrl,
                placeholder: "请输入服务器地址",
                onOk: (value, cb) => {
                    if (value) {
                        if (!$A.leftExists(value, "http://") && !$A.leftExists(value, "https://")) {
                            value = "http://" + value;
                        }
                        if (!$A.rightExists(value, "/api/")) {
                            value = value + ($A.rightExists(value, "/") ? "api/" : "/api/");
                        }
                        this.$store.dispatch("call", {
                            url: value + 'system/setting',
                        }).then(() => {
                            this.$store.state.method.setStorage("cacheServerUrl", value)
                            window.location.reload();
                        }).catch(({msg}) => {
                            $A.modalError(msg || "服务器地址无效", 301);
                            cb()
                        });
                        return;
                    }
                    this.$store.state.method.setStorage("cacheServerUrl", "")
                    window.location.reload();
                }
            });
        },

        onServerUrlClear() {
            this.$store.state.method.setStorage("cacheServerUrl", "")
            window.location.reload();
        },

        onBlur() {
            if (this.loginType != 'login' || !this.email) {
                this.codeNeed = false;
                return;
            }
            this.loadIng++;
            this.$store.dispatch("call", {
                url: 'users/login/needcode',
                data: {
                    email: this.email,
                },
            }).then(() => {
                this.loadIng--;
                this.reCode();
                this.codeNeed = true;
            }).catch(() => {
                this.loadIng--;
                this.codeNeed = false;
            });
        },

        onLogin() {
            if (!this.email) {
                return;
            }
            if (!this.password) {
                return;
            }
            if (this.loginType == 'reg') {
                if (this.password != this.password2) {
                    $A.noticeError("确认密码输入不一致");
                    return;
                }
            }
            this.loadIng++;
            this.$store.dispatch("call", {
                url: 'users/login',
                data: {
                    type: this.loginType,
                    email: this.email,
                    password: this.password,
                    code: this.code,
                    invite: this.invite,
                },
            }).then(({data}) => {
                this.loadIng--;
                this.$store.state.method.setStorage("cacheLoginEmail", this.email)
                this.$store.dispatch("handleClearCache", data).then(() => {
                    this.goNext1();
                }).catch(() => {
                    this.goNext1();
                });
            }).catch(({data, msg}) => {
                this.loadIng--;
                $A.noticeError(msg);
                if (data.code === 'need') {
                    this.reCode();
                    this.codeNeed = true;
                }
            });
        },

        goNext1() {
            this.loginJump = true;
            if (this.loginType == 'login') {
                this.goNext2();
            } else {
                // 新注册自动创建项目
                this.$store.dispatch("call", {
                    url: 'project/add',
                    data: {
                        name: this.$L('个人项目'),
                        desc: this.$L('注册时系统自动创建项目，你可以自由删除。')
                    },
                }).then(() => {
                    this.goNext2();
                }).catch(() => {
                    this.goNext2();
                });
            }
        },

        goNext2() {
            let fromUrl = decodeURIComponent($A.getObject(this.$route.query, 'from'));
            if (fromUrl) {
                window.location.replace(fromUrl);
            } else {
                this.goForward({path: '/manage/dashboard'}, true);
            }
        }
    }
}
</script>
