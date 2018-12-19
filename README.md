# responseListAndManage for LimeSurvey

Allow to manage response in different way for admin and for particpant with tokens

**This plugin is compatible with LimeSurvey 2.76 and 3.0 and up version.** This plugin was testes with version 2.76, 3.6 and 3.14.

## Installation

To use this plugin, you **need** [getQuestionInformation](https://gitlab.com/SondagesPro/coreAndTools/getQuestionInformation) and [reloadAnyResponse](https://gitlab.com/SondagesPro/coreAndTools/reloadAnyResponse) plugins activated.

### Via GIT
- Go to your LimeSurvey Directory (version up to 2.50)
- Clone in plugins/responseListAndManage directory

### Via ZIP dowload
- Get the file [responseListAndManage.zip](https://extensions.sondages.pro/IMG/auto/responseListAndManage.zip)
- Extract : `unzip responseListAndManage.zip`
- Move the directory to plugins/ directory inside LimeSUrvey


## Usage

A new login page can be used for admin. This page can show whole active survey where this plugin is activated.

For each survey : if survey have token and is not anonymous : user with token can “log in” with their token and manage their response.

Two elements are added in survey tools menu : one for survey alternate management, and one for managing settings of alternate management.

With survey with token table : you can set group and group manager. Then particpant can view, add, update response. According to survey settings about anonymous, token answer persistance and allow multiple response.

## Issues and feature

All issue and merge request must be done in [base repo](https://gitlab.com/SondagesPro/managament/responseListAndManage) (currently gitlab).

Issue must be posted with complete information : LimeSurvey version and build number, web server version, PHP version, SQL type and version number … 
**Reminder:** no warranty of functionnal system operation is offered on this software. If you want a professional offer: please [contact Sondages Pro](https://extensions.sondages.pro/about/contact.html).

## Home page & Copyright
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2018 Denis Chenu <http://sondages.pro>
- Copyright © 2018 DRAAF Bourgogne - Franche-Comté <http://draaf.bourgogne-franche-comte.agriculture.gouv.fr/>
- Licence : GNU Affero General Public License <https://www.gnu.org/licenses/agpl-3.0.html>

