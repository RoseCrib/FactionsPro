<?php

namespace FactionsPro;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (strtolower($command->getName()) !== "f" || empty($args)) {
            $sender->sendMessage($this->plugin->formatMessage("Te rog foloseste /f help pentru a vedea lista de comenzi"));
            return true;
        }
        if (strtolower($args[0]) == "help") {
            $sender->sendMessage(TextFormat::YELLOW . "\n/f about\n/f accept\n/f overclaim [Preia plotul facțiunii solicitate]\n/f claim\n/f create <nume>\n/f del\n/f demote <jucator>\n/f deny");
            $sender->sendMessage(TextFormat::YELLOW . "\n/f home\n/f help <pagina>\n/f info\n/f info <factiune>\n/f invite <jucator>\n/f kick <jucator>\n/f leader <jucator>\n/f leave");
            $sender->sendMessage(TextFormat::YELLOW . "\n/f sethome\n/f unclaim\n/f unsethome\n/f ourmembers - {Membrii + Status}\n/f ourofficers - {Officeri + Status}\n/f ourleader - {Leader + Status}\n/f allies - {Aliații facțiunii tale");
            $sender->sendMessage(TextFormat::YELLOW . "\n/f desc\n/f promote <jucator>\n/f allywith <factiune>\n/f breakalliancewith <factiune>\n\n/f allyok [Accepta sau refuza o alianță]\n/f allyno [Refuza o alianță]\n/f allies <factiune> - {Aliații facțiunii alese}");
            $sender->sendMessage(TextFormat::YELLOW . "\n/f membersof <factiune>\n/f officersof <factiune>\n/f leaderof <factiune>\n/f say <trimite message to everyone in your faction>\n/f pf <jucator>\n/f topfactions");
            $sender->sendMessage(TextFormat::YELLOW . "\n/f forceunclaim <factiune> [Elimina ca acest plot sa nu mai fie al facțiuni alese - NEED OP]\n\n/f forcedelete <factiune> [Sterge o factiune aleasa - NEED OP]");
            return true;
        }
        if (!$sender instanceof Player || ($sender->isOp() && $this->plugin->prefs->get("AllowOpToChangeFactionPower"))) {
            if (strtolower($args[0]) == "addpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f addpower <nume factiune> <putere>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->addFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage("Putere " . $args[2] . " adăugat la facțiunea " . $args[1]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea " . $args[1] . " nu exista"));
                }
            }
            if (strtolower($args[0]) == "setpower") {
                if (!isset($args[1]) || !isset($args[2]) || !$this->alphanum($args[1]) || !is_numeric($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f setpower <nume facțiune> <putere>"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $this->plugin->setFactionPower($args[1], $args[2]);
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea " . $args[1] . " Putere setata la " . $args[2]));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea " . $args[1] . " nu exista"));
                }
            }
            if (!$sender instanceof Player) return true;
        }
        $playerName = $sender->getPlayer()->getName();

            ///////////////////////////////// WAR /////////////////////////////////

            if ($args[0] == "war") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f war <nume facțiune:tp>"));
                    return true;
                }
                if (strtolower($args[1]) == "tp") {
                    foreach ($this->plugin->wars as $r => $f) {
                        $fac = $this->plugin->getPlayerFaction($playerName);
                        if ($r == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$f][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                        if ($f == $fac) {
                            $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                            $tper = $this->plugin->war_players[$r][$x];
                            $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                            return true;
                        }
                    }
                    $sender->sendMessage("Trebuie să fii într-un război pentru a face asta");
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("Puteți utiliza doar litere și numere"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea nu exista"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Doar liderul tău de facțiune poate începe războaie"));
                    return true;
                }
                if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta nu este un dușman al $args[1]"));
                    return true;
                } else {
                    $factionName = $args[1];
                    $sFaction = $this->plugin->getPlayerFaction($playerName);
                    foreach ($this->plugin->war_req as $r => $f) {
                        if ($r == $args[1] && $f == $sFaction) {
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                $task = new FactionWar($this->plugin, $r);
                                $handler = $this->plugin->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                $task->setHandler($handler);
                                $p->sendMessage("Războiul împotriva $factionName și $sFaction a inceput!");
                                if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                    $this->plugin->war_players[$sFaction][] = $p->getName();
                                }
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    $this->plugin->war_players[$factionName][] = $p->getName();
                                }
                            }
                            $this->plugin->wars[$factionName] = $sFaction;
                            unset($this->plugin->war_req[strtolower($args[1])]);
                            return true;
                        }
                    }
                    $this->plugin->war_req[$sFaction] = $factionName;
                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                            if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                $p->sendMessage("$sFactiunea vrea sa înceapă un război,scrie '/f war $sFaction' pentru a incepe!");
                                $sender->sendMessage("Războaiele intre facțiuni a fost refuzat");
                                return true;
                            }
                        }
                    }
                    $sender->sendMessage("Leaderul factiunii nu este online.");
                    return true;
                }
            }

            /////////////////////////////// CREATE ///////////////////////////////

            if ($args[0] == "create") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f create <nume factiune>"));
                    return true;
                }
                if (!($this->alphanum($args[1]))) {
                    $sender->sendMessage($this->plugin->formatMessage("Puteți utiliza doar litere și numere"));
                    return true;
                }
                if ($this->plugin->isNameBanned($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Acest nume nu e permis"));
                    return true;
                }
                if ($this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea exista deja"));
                    return true;
                }
                if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                    $sender->sendMessage($this->plugin->formatMessage("acest nume e prea lung, va rugam încercați din nou"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Mai întâi trebuie să părăsiți facțiunea"));
                    return true;
                } else {
                    $factionName = $args[1];
                    $rank = "Leader";
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", $playerName);
                    $stmt->bindValue(":faction", $factionName);
                    $stmt->bindValue(":rank", $rank);
                    $result = $stmt->execute();
                    $this->plugin->updateAllies($factionName);
                    $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                    $this->plugin->updateTag($sender->getName());
                    $sender->sendMessage($this->plugin->formatMessage("Factiune creată cu succes", true));
                    return true;
                }
            }

            /////////////////////////////// INVITE ///////////////////////////////

            if ($args[0] == "invite") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f invite <jucator>"));
                    return true;
                }
                if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($playerName))) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea este plină, te rog să dai afara un jucător să se facă loc"));
                    return true;
                }
                $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                if (!($invited instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucătorul nu e online"));
                    return true;
                }
                if ($this->plugin->isInFaction($invited->getName()) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucătorul se află în prezent în facțiune"));
                    return true;
                }
                if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                    if (!($this->plugin->isOfficer($playerName) || $this->plugin->isLeader($playerName))) {
                        $sender->sendMessage($this->plugin->formatMessage("Numai liderul / ofițerii dvs. pot invita"));
                        return true;
                    }
                }
                if ($invited->getName() == $playerName) {

                    $sender->sendMessage($this->plugin->formatMessage("Nu te poți invita in propria ta facție"));
                    return true;
                }

                $factionName = $this->plugin->getPlayerFaction($playerName);
                $invitedName = $invited->getName();
                $rank = "Member";

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                $stmt->bindValue(":player", $invitedName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":invitedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited", true));
                $invited->sendMessage($this->plugin->formatMessage("You have been invited to $factionName. Type '/f accept' or '/f deny' into chat to accept or deny!", true));
            }

            /////////////////////////////// LEADER ///////////////////////////////

            if ($args[0] == "leader") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f leader <jucator>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a utiliza acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți leader pentru a utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Adăugați jucatorii in facțiune"));
                    return true;
                }
                if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucator nu e online"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {

                    $sender->sendMessage($this->plugin->formatMessage("Nu poți transfera conducerea la tine"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $playerName);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();

                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Leader");
                $result = $stmt->execute();


                $sender->sendMessage($this->plugin->formatMessage("You are no longer leader", true));
                $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("You are now leader \nof $factionName!", true));
                $this->plugin->updateTag($sender->getName());
                $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
            }

            /////////////////////////////// PROMOTE ///////////////////////////////

            if ($args[0] == "promote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Usage: /f promote <player>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a utiliza acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a putea utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucatorul nu e in facțiunea ta"));
                    return true;
                }
                $promotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                if ($promotee instanceof Player && $promotee->getName() == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Nu te poti promova pe tine singur"));
                    return true;
                }

                if ($this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucatorul este deja Officer"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Officer");
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("$args[1] a fost promovat la  Officer", true));

                if ($promotee instanceof Player) {
                    $promotee->sendMessage($this->plugin->formatMessage("Ai fost promovat la officer of $factionName!", true));
                    $this->plugin->updateTag($promotee->getName());
                    return true;
                }
            }

            /////////////////////////////// DEMOTE ///////////////////////////////

            if ($args[0] == "demote") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f demote <jucator>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucătorul nu este in aceasta facțiune"));
                    return true;
                }

                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Nu iti poti da down"));
                    return true;
                }
                if (!$this->plugin->isOfficer($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucatorul este deja membru"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                $stmt->bindValue(":player", $args[1]);
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":rank", "Member");
                $result = $stmt->execute();
                $demotee = $this->plugin->getServer()->getPlayerExact($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("$args[1] a primit down la membru", true));
                if ($demotee instanceof Player) {
                    $demotee->sendMessage($this->plugin->formatMessage("Ai primit down in facțiunea $factionName!", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }

            /////////////////////////////// KICK ///////////////////////////////

            if ($args[0] == "kick") {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f kick <jucator>"));
                    return true;
                }
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți lider pentru a utiliza acest lucru"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) != $this->plugin->getPlayerFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucătorul nu e in aceasta facțiune"));
                    return true;
                }
                if ($args[1] == $sender->getName()) {
                    $sender->sendMessage($this->plugin->formatMessage("Nu te poti da afara"));
                    return true;
                }
                $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                $factionName = $this->plugin->getPlayerFaction($playerName);
                $stmt = $this->plugin->db->prepare("DELETE FROM master WHERE player = :playername;");
                $stmt->bindvalue(":playername", $args[1]);
                $stmt->execute();

                $sender->sendMessage($this->plugin->formatMessage("Ai dat afara pe $args[1]", true));
                $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                if ($kicked instanceof Player) {
                    $kicked->sendMessage($this->plugin->formatMessage("Ai fost afara din facțiunea \n $factionName", true));
                    $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    return true;
                }
            }


            /////////////////////////////// CLAIM ///////////////////////////////

            if (strtolower($args[0]) == 'claim') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fii într-o facțiune."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader sa puteti utiliza acest lucru."));
                    return true;
                }
                if (!in_array($sender->getPlayer()->getLevel()->getName(), $this->plugin->prefs->get("ClaimWorlds"))) {
                    $sender->sendMessage($this->plugin->formatMessage("Poti revendicat pamanturi pe Faction Worlds: " . implode(" ", $this->plugin->prefs->get("ClaimWorlds"))));
                    return true;
                }

                if ($this->plugin->inOwnPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea dvs. a revendicat deja această zonă."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                    $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                        $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage($this->plugin->formatMessage("Ai nevoie de $needed_players jucători pentru a revedinca aceasta zona "));
                    return true;
                }
                if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                    $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                    $faction_power = $this->plugin->getFactionPower($faction);
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta nu are destula PUTERE pentru a revendica aceasta zona."));
                    $sender->sendMessage($this->plugin->formatMessage("$needed_power puterea este necesară, dar facțiunea ta are numai $faction_power PUTERE."));
                    return true;
                }

                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ()); 
                if ($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

                    return true;
                }

                $sender->sendMessage($this->plugin->formatMessage("Obținerea coordonatelor dvs...", true));
                $plot_size = $this->plugin->prefs->get("PlotSize");
                $faction_power = $this->plugin->getFactionPower($faction);
                $sender->sendMessage($this->plugin->formatMessage("Zona ta a fost revendicata.", true));
            }
            if (strtolower($args[0]) == 'plotinfo') {
                $x = floor($sender->getX());
                $y = floor($sender->getY());
                $z = floor($sender->getZ());
                if (!$this->plugin->isInPlot($sender)) {
                    $sender->sendMessage($this->plugin->formatMessage("Aceasta zona nu este revendicata de nimeni. O puteți revendica tastând /f claim ", true));
                    return true;
                }

                $fac = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                $power = $this->plugin->getFactionPower($fac);
                $sender->sendMessage($this->plugin->formatMessage("Aceasta zona este revendicata de facțiunea $fac cu $power PUTERE"));
            }
            if (strtolower($args[0]) == 'topfactions') {
                $this->plugin->sendListOfTop10FactionsTo($sender);
            }
            if (strtolower($args[0]) == 'forcedelete') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f forcedelete <factiune>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu există."));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să aveți OP pentru a face acest lucru."));
                    return true;
                }
                $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                $sender->sendMessage($this->plugin->formatMessage("Facțiunea nedorită a fost ștearsă cu succes si zonele revendicate au fost nerevendicate!", true));
            }
            if (strtolower($args[0]) == 'addstrto') {
                if (!isset($args[1]) or !isset($args[2])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f addstrto <factiune> <PUTERE>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu există."));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa aveti OP pentru a face acest lucru."));
                    return true;
                }
                $this->plugin->addFactionPower($args[1], $args[2]);
                $sender->sendMessage($this->plugin->formatMessage("Ati adaugat $args[2] PUTERE la $args[1]", true));
            }
            if (strtolower($args[0]) == 'pf') {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f pf <jucator>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Jucătorul selectat nu se află în facțiune sau nu există."));
                    $sender->sendMessage($this->plugin->formatMessage("Asigurați-vă că numele jucătorului selectat este scris corect"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("-$args[1] este in $faction-", true));
            }

            if (strtolower($args[0]) == 'overclaim') {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fii într-o facțiune."));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți leader pentru a utiliza acest lucru."));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($playerName);
                if ($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")) {

                    $needed_players = $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                        $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage($this->plugin->formatMessage("Ai nevoie de $needed_players jucători in factiune pentru a revendica aceasta zona revendicata de o alta facțiune"));
                    return true;
                }
                if ($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")) {
                    $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                    $faction_power = $this->plugin->getFactionPower($faction);
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu are destula PUTERE pentru a revendica aceasta zona"));
                    $sender->sendMessage($this->plugin->formatMessage("$needed_power PUTERE este necesară, dar facțiunea dvs. are numai $faction_power PUTERE."));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Obținerea coordonatelor...", true));
                $x = floor($sender->getX());
                $z = floor($sender->getZ());
                $level = $sender->getLevel()->getName();
                if ($this->plugin->prefs->get("EnableOverClaim")) {
                    if ($this->plugin->isInPlot($sender)) {
                        $faction_victim = $this->plugin->factionFromPoint($x, $z, $sender->getPlayer()->getLevel()->getName());
                        $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                        $faction_ours = $this->plugin->getPlayerFaction($playerName);
                        $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                        if ($this->plugin->inOwnPlot($sender)) {
                            $sender->sendMessage($this->plugin->formatMessage("Nu-ți poți exclude propria ta zona"));
                            return true;
                        } else {
                            if ($faction_ours_power < $faction_victim_power) {
                                $sender->sendMessage($this->plugin->formatMessage("Nu puteți exclude zona din facțiunea $faction_victim pentru că PUTEREA ta este mai mică decât a lor."));
                                return true;
                            } else {
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                $this->plugin->newPlot($faction_ours, $x + $arm, $z + $arm, $x - $arm, $z - $arm, $level);
                                $sender->sendMessage($this->plugin->formatMessage("Zona din facțiunea $faction_victim a fost revendicat. Acum este al tău.", true));
                                if ($this->plugin->prefs->get("OverClaimCostsPower")) {
                                    $this->plugin->setFactionPower($faction_ours, $faction_ours_power - $faction_victim_power);
                                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta s-a folosit $faction_victim_power de PUTERE la excludere $faction_victim", true));
                                }
                                return true;
                            }
                        }
                    } else {
                        $sender->sendMessage($this->plugin->formatMessage("Trebuie să vă aflați în o zona de facțiune."));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Excluderea altei zone de facțiune este dezactivată"));
                    return true;
                }
            }


            /////////////////////////////// UNCLAIM ///////////////////////////////

            if (strtolower($args[0]) == "unclaim") {
                if (!$this->plugin->isInFaction($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiti într-o facțiune"));
                    return true;
                }
                if (!$this->plugin->isLeader($sender->getName())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti leader/officer sa puteti utiliza acest lucru"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                $sender->sendMessage($this->plugin->formatMessage("ai nerevendicat zona ta", true));
            }

            /////////////////////////////// DESCRIPTION ///////////////////////////////

            if (strtolower($args[0]) == "desc") {
                if ($this->plugin->isInFaction($sender->getName()) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a utiliza acest lucru!"));
                    return true;
                }
                if ($this->plugin->isLeader($playerName) == false) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru s utiliza acest lucru"));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Introduceți mesajul în chat. Nu va fi vizibil pentru alți jucători", true));
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                $stmt->bindValue(":player", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
            }

            /////////////////////////////// ACCEPT ///////////////////////////////

            if (strtolower($args[0]) == "accept") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Nu ai fost invitat la nicio facțiune"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $faction = $array["faction"];
                    $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                    $stmt->bindValue(":player", ($playerName));
                    $stmt->bindValue(":faction", $faction);
                    $stmt->bindValue(":rank", "Member");
                    $result = $stmt->execute();
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Ai intrat cu succes in facțiunea $faction", true));
                    $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $inviter = $this->plugin->getServer()->getPlayerExact($array["invitedby"]);
                    if ($inviter !== null) $inviter->sendMessage($this->plugin->formatMessage("$playerName a intrat in facțiune", true));
                    $this->plugin->updateTag($sender->getName());
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Invitatia a expirat"));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$playerName';");
                }
            }

            /////////////////////////////// DENY ///////////////////////////////

            if (strtolower($args[0]) == "deny") {
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Nu ai fost invitat la nicio facțiune"));
                    return true;
                }
                $invitedTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("invitație refuzata", true));
                    $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$playerName a refuzat invitația"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Invitatia a expirat"));
                    $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                }
            }

            /////////////////////////////// DELETE ///////////////////////////////

            if (strtolower($args[0]) == "del") {
                if ($this->plugin->isInFaction($playerName) == true) {
                    if ($this->plugin->isLeader($playerName)) {
                        $faction = $this->plugin->getPlayerFaction($playerName);
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                        $sender->sendMessage($this->plugin->formatMessage("Factiunea a fost ștearsă cu succes si zonele tale revendicate au devenit nerevendicate", true));
                        $this->plugin->updateTag($sender->getName());
                        unset($this->plugin->factionChatActive[$playerName]);
                        unset($this->plugin->allyChatActive[$playerName]);
                    } else {
                        $sender->sendMessage($this->plugin->formatMessage("Nu ești leader!"));
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Nu esti in o facțiune!"));
                }
            }

            /////////////////////////////// LEAVE ///////////////////////////////

            if (strtolower($args[0] == "leave")) {
                if ($this->plugin->isLeader($playerName) == false) {
                    $remove = $sender->getPlayer()->getNameTag();
                    $faction = $this->plugin->getPlayerFaction($playerName);
                    $name = $sender->getName();
                    $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                    $sender->sendMessage($this->plugin->formatMessage("Ai iesit cu succes din facțiunea $faction", true));
                    $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                    $this->plugin->updateTag($sender->getName());
                    unset($this->plugin->factionChatActive[$playerName]);
                    unset($this->plugin->allyChatActive[$playerName]);
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Pentru a ieși trebuie sa stergi facțiunea sau sa daruiesti altcuiva leader"));
                }
            }

            /////////////////////////////// SETHOME ///////////////////////////////

            if (strtolower($args[0] == "sethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a seta casa"));
                    return true;
                }
                $factionName = $this->plugin->getPlayerFaction($sender->getName());
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z, world) VALUES (:faction, :x, :y, :z, :world);");
                $stmt->bindValue(":faction", $factionName);
                $stmt->bindValue(":x", $sender->getX());
                $stmt->bindValue(":y", $sender->getY());
                $stmt->bindValue(":z", $sender->getZ());
                $stmt->bindValue(":world", $sender->getLevel()->getName());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("Casa setata", true));
            }

            /////////////////////////////// UNSETHOME ///////////////////////////////

            if (strtolower($args[0] == "unsethome")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți în facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți lider pentru a neseta casa"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
                $sender->sendMessage($this->plugin->formatMessage("Home unset", true));
            }

            /////////////////////////////// HOME ///////////////////////////////

            if (strtolower($args[0] == "home")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiți in facțiune pentru a face acest lucru"));
                    return true;
                }
                $faction = $this->plugin->getPlayerFaction($sender->getName());
                $result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (!empty($array)) {
                    if ($array['world'] === null || $array['world'] === "") {
                        $sender->sendMessage($this->plugin->formatMessage("Casa nu poate fi gasita deoarece nu este o lume cu acest nume, vă rugăm să o ștergeți și să o faceți din nou"));
                        return true;
                    }
                    if (Server::getInstance()->loadLevel($array['world']) === false) {
                        $sender->sendMessage($this->plugin->formatMessage("Lumea '" . $array['world'] . "'' Nu poate fi gasita"));
                        return true;
                    }
                    $level = Server::getInstance()->getLevelByName($array['world']);
                    $sender->getPlayer()->teleport(new Position($array['x'], $array['y'], $array['z'], $level));
                    $sender->sendMessage($this->plugin->formatMessage("Teleportat la casa factiunii", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Casa nu a fost setata"));
                }
            }

            /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
            if (strtolower($args[0] == "ourmembers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie ss fiti in factiune pentru a face acest lucru"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Member");
            }
            if (strtolower($args[0] == "membersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f membersof <factiune>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu există"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
            }
            if (strtolower($args[0] == "ourofficers")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in factiune pentru a face acest lucru"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Officer");
            }
            if (strtolower($args[0] == "officersof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f officersof <factiune>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu există"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
            }
            if (strtolower($args[0] == "ourleader")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in o facțiune sa puteti face acest lucru"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($playerName), "Leader");
            }
            if (strtolower($args[0] == "leaderof")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Folosire: /f leaderof <factiune>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitata nu exista"));
                    return true;
                }
                $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
            }
            if (strtolower($args[0] == "say")) {
                if (true) {
                    $sender->sendMessage($this->plugin->formatMessage("/f say este dezactivat"));
                    return true;
                }
                if (!($this->plugin->isInFaction($playerName))) {

                    $sender->sendMessage($this->plugin->formatMessage("Trebuie să fiți într-o facțiune pentru a trimite un mesaj in facțiune"));
                    return true;
                }
                $r = count($args);
                $row = array();
                $rank = "";
                $f = $this->plugin->getPlayerFaction($playerName);

                if ($this->plugin->isOfficer($playerName)) {
                    $rank = "Officer";
                } else if ($this->plugin->isLeader($playerName)) {
                    $rank = "Leader";
                }
                $message = "-> ";
                for ($i = 0; $i < $r - 1; $i = $i + 1) {
                    $message = $message . $args[$i + 1] . " ";
                }
                $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                    $row[$i]['player'] = $resultArr['player'];
                    $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                    if ($p instanceof Player) {
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$playerName> " . ": " . TextFormat::RESET);
                        $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                    }
                }
            }


            ////////////////////////////// ALLY SYSTEM ////////////////////////////////
            if (strtolower($args[0] == "enemywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f enemywith <factiune>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a putea face acest lucru"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicita nu exista"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("O facțiune nu poate fi un dușman al ei"));
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta este un aliat al facțiunii $args[1]"));
                    return true;
                }
                if ($this->plugin->areEnemies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta este deja un dușman al facțiuni $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Leader-ul facțiunii solicitate nu este conectat"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("Leader-ul factiunii $fac a declarat că facțiunile tale sunt dușmani", true));
                }
                $this->plugin->setEnemies($fac, $args[1]);
                $sender->sendMessage($this->plugin->formatMessage("Acum sunteti dușmani cu facțiunea $args[1]!", true));
            }
            if (strtolower($args[0] == "notenemy")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("folosire: /f notenemy <factiune>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in factiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea solicitata nu exista"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->unsetEnemies($fac, $args[1]);
                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Leader-ul facțiuni solicitate nu este conectat"));
                } else {
                    $leader->sendMessage($this->plugin->formatMessage("Leader-ul facțiuni $fac a declarat ca facțiunea ta nu mai este dușmană", true));
                }
                $sender->sendMessage($this->plugin->formatMessage("Nu mai sunteți dușman cu $args[1]!", true));
            }
            if (strtolower($args[0] == "allywith")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Folosire: /f allywith <factiune>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in o facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea solicitata nu exista"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta nu se poate alia cu ea însăși"));
                    return true;
                }
                if ($this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta este deja aliata cu $args[1]"));
                    return true;
                }
                $fac = $this->plugin->getPlayerFaction($playerName);
                $leaderName = $this->plugin->getLeader($args[1]);
                if (!isset($fac) || !isset($leaderName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea nu a fost gasita"));
                    return true;
                }
                $leader = $this->plugin->getServer()->getPlayerExact($leaderName);
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);

                if (!($leader instanceof Player)) {
                    $sender->sendMessage($this->plugin->formatMessage("Leader-ul facțiuni solicitate nu este conectat"));
                    return true;
                }
                if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată are numărul maxim de aliații", false));
                    return true;
                }
                if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta are numarul maxim de aliații", false));
                    return true;
                }
                $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                $stmt->bindValue(":player", $leader->getName());
                $stmt->bindValue(":faction", $args[1]);
                $stmt->bindValue(":requestedby", $sender->getName());
                $stmt->bindValue(":timestamp", time());
                $result = $stmt->execute();
                $sender->sendMessage($this->plugin->formatMessage("Ai solicitat să te aliezi cu facțiunea $args[1]!\nAsteapta ca Leader-ul sa răspundă...", true));
                $leader->sendMessage($this->plugin->formatMessage("Leader-ul facțiuni $fac a cerut o alianță.\nScrie /f allyok pentru a accepta sau /f allyno pentru a refuza", true));
            }
            if (strtolower($args[0] == "breakalliancewith") or strtolower($args[0] == "notally")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Folosire: /f breakalliancewith <factiune>"));
                    return true;
                }
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in o facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitata nu exista"));
                    return true;
                }
                if ($this->plugin->getPlayerFaction($playerName) == $args[1]) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta nu poate rupe alianța cu ea însăși"));
                    return true;
                }
                if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($playerName), $args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta nu este aliată cu facțiunea $args[1]"));
                    return true;
                }

                $fac = $this->plugin->getPlayerFaction($playerName);
                $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                $this->plugin->deleteAllies($fac, $args[1]);
                $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                $this->plugin->updateAllies($fac);
                $this->plugin->updateAllies($args[1]);
                $sender->sendMessage($this->plugin->formatMessage("Facțiunea $fac nu mai este aliat cu facțiunea $args[1]", true));
                if ($leader instanceof Player) {
                    $leader->sendMessage($this->plugin->formatMessage("Leader-ul facțiuni of $fac a rupt alianța cu facțiunea ta $args[1]", false));
                }
            }
            if (strtolower($args[0] == "forceunclaim")) {
                if (!isset($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Folosire: /f forceunclaim <factiune>"));
                    return true;
                }
                if (!$this->plugin->factionExists($args[1])) {
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu exista"));
                    return true;
                }
                if (!($sender->isOp())) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa ai OP pentru a face acest lucru."));
                    return true;
                }
                $sender->sendMessage($this->plugin->formatMessage("Ai nerevendicat zona luata de alta facțiune cu OP $args[1]"));
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
            }

            if (strtolower($args[0] == "allies")) {
                if (!isset($args[1])) {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti în o facțiune sa poti face acest lucru"));
                        return true;
                    }

                    $this->plugin->updateAllies($this->plugin->getPlayerFaction($playerName));
                    $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($playerName));
                } else {
                    if (!$this->plugin->factionExists($args[1])) {
                        $sender->sendMessage($this->plugin->formatMessage("Facțiunea solicitată nu exista"));
                        return true;
                    }
                    $this->plugin->updateAllies($args[1]);
                    $this->plugin->getAllAllies($sender, $args[1]);
                }
            }
            if (strtolower($args[0] == "allyok")) {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in o facțiune pentru a face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a face acest lucru"));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu a fost solicitată să nu se alieze nici unei facțiuni"));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->setAllies($requested_fac, $sender_fac);
                    $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $this->plugin->updateAllies($requested_fac);
                    $this->plugin->updateAllies($sender_fac);
                    $this->plugin->unsetEnemies($requested_fac, $sender_fac);
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea ta a fost aliată cu facțiunea $requested_fac", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$playerName din facțiunea $sender_fac a acceptat alianța!", true));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Solicitarea a expirat"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }
            if (strtolower($args[0]) == "allyno") {
                if (!$this->plugin->isInFaction($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti in o facțiune pentru a putea face acest lucru"));
                    return true;
                }
                if (!$this->plugin->isLeader($playerName)) {
                    $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiti Leader pentru a face acest lucru"));
                    return true;
                }
                $lowercaseName = strtolower($playerName);
                $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                $array = $result->fetchArray(SQLITE3_ASSOC);
                if (empty($array) == true) {
                    $sender->sendMessage($this->plugin->formatMessage("Factiunea ta nu a fost solicitată să nu se alieze nici unei facțiuni"));
                    return true;
                }
                $allyTime = $array["timestamp"];
                $currentTime = time();
                if (($currentTime - $allyTime) <= 60) { //This should be configurable
                    $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                    $sender_fac = $this->plugin->getPlayerFaction($playerName);
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                    $sender->sendMessage($this->plugin->formatMessage("Facțiunea dvs. a refuzat cu succes solicitarea de alianță.", true));
                    $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$playerName din facțiunea $sender_fac a refuzat alianța!"));
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Solicitarea a expirat"));
                    $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                }
            }


            /////////////////////////////// ABOUT ///////////////////////////////

            if (strtolower($args[0] == 'about')) {
                $sender->sendMessage(TextFormat::GREEN . "[ORIGINAL] FactionsPro v1.3.2 by " . TextFormat::BOLD . "RoseCrib");
                $sender->sendMessage(TextFormat::GOLD . "[MODDED] This version by " . TextFormat::BOLD . "RoseCrib");
            }
            ////////////////////////////// CHAT ////////////////////////////////
            if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("Toate chaturile facționale sunt dezactivate", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->factionChatActive[$playerName])) {
                        unset($this->plugin->factionChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Chat-ul factiunii dezactivat", false));
                        return true;
                    } else {
                        $this->plugin->factionChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("§eChat-ul factiunii activat", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Nu ești într-o facțiune"));
                    return true;
                }
            }
            if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                if (!$this->plugin->prefs->get("AllowChat")) {
                    $sender->sendMessage($this->plugin->formatMessage("Chatul factiuniilor aliate a fost dezactivat", false));
                    return true;
                }

                if ($this->plugin->isInFaction($playerName)) {
                    if (isset($this->plugin->allyChatActive[$playerName])) {
                        unset($this->plugin->allyChatActive[$playerName]);
                        $sender->sendMessage($this->plugin->formatMessage("Chat-ul aliantei dezactivat ", false));
                        return true;
                    } else {
                        $this->plugin->allyChatActive[$playerName] = 1;
                        $sender->sendMessage($this->plugin->formatMessage("§eChat-ul aliantei activat", false));
                        return true;
                    }
                } else {
                    $sender->sendMessage($this->plugin->formatMessage("Nu esti într-o facțiune"));
                    return true;
                }
            }

            /////////////////////////////// INFO ///////////////////////////////

            if (strtolower($args[0]) == 'info') {
                if (isset($args[1])) {
                    if (!(ctype_alnum($args[1])) or !($this->plugin->factionExists($args[1]))) {
                        $sender->sendMessage($this->plugin->formatMessage("Factiunea nu exista"));
                        $sender->sendMessage($this->plugin->formatMessage("Asigurați-vă că numele facțiunii selectate este ABSOLUT CORECT."));
                        return true;
                    }
                    $faction = $args[1];
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "-------INFORMATII-------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|[Factiune]| : " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|^Membrii^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|&Putere&| : " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|*Descriere*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "-------INFORMATII-------" . TextFormat::RESET);
                } else {
                    if (!$this->plugin->isInFaction($playerName)) {
                        $sender->sendMessage($this->plugin->formatMessage("Trebuie sa fiți in o facțiune sa poti face acest lucru"));
                        return true;
                    }
                    $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                    $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                    $array = $result->fetchArray(SQLITE3_ASSOC);
                    $power = $this->plugin->getFactionPower($faction);
                    $message = $array["message"];
                    $leader = $this->plugin->getLeader($faction);
                    $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|[Factiune]| : " . TextFormat::GREEN . "$faction" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|(Leader)| : " . TextFormat::YELLOW . "$leader" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|^Membrii^| : " . TextFormat::LIGHT_PURPLE . "$numPlayers" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|&Putere&| : " . TextFormat::RED . "$power" . " STR" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "|*Descriere*| : " . TextFormat::AQUA . TextFormat::UNDERLINE . "$message" . TextFormat::RESET);
                    $sender->sendMessage(TextFormat::RED . TextFormat::ITALIC . "-------INFORMATION-------" . TextFormat::RESET);
                }
                return true;
            }
            if ($this->plugin->prefs->get("EnableMap") && (strtolower($args[0]) == "map" or strtolower($args[0]) == "m")) {
                $factionPlots = $this->plugin->getNearbyPlots($sender);
                if ($factionPlots == null) {
                    $sender->sendMessage(TextFormat::RED . "Nu au fost găsite facțiuni in apropiere");
                    return true;
                }
                $playerFaction = $this->plugin->getPlayerFaction(($sender->getName()));
                $found = false;
                foreach ($factionPlots as $key => $faction) {
                    $plotFaction = $factionPlots[$key]['faction'];
                    if ($plotFaction == $playerFaction) {
                        continue;
                    }
                    if ($this->plugin->isInPlot($sender)) {
                        $inWhichPlot = $this->plugin->factionFromPoint($sender->getX(), $sender->getZ(), $sender->getLevel()->getName());
                        if ($inWhichPlot == $plotFaction) {
                            $sender->sendMessage(TextFormat::YELLOW . "Ești în facțiunea " . $plotFaction . "'s zona");
                            $found = true;
                            continue;
                        }
                    }
                    $found = true;
                    $x1 = $factionPlots[$key]['x1'];
                    $x2 = $factionPlots[$key]['x2'];
                    $z1 = $factionPlots[$key]['z1'];
                    $z2 = $factionPlots[$key]['z2'];
                    $plotX = $x1 + ($x2 - $x1) / 2;
                    $plotZ = $z1 + ($z2 - $z1) / 2;
                    $deltaX = $plotX - $sender->getX();
                    $deltaZ = $plotZ - $sender->getZ();
                    $bearing = rad2deg(atan2($deltaZ, $deltaX));
                    if ($bearing >= -22.5 && $bearing < 22.5) $direction = "south";
                    else if ($bearing >= 22.5 && $bearing < 67.5) $direction = "southwest";
                    else if ($bearing >= 67.5 && $bearing < 112.5) $direction = "west";
                    else if ($bearing >= 112.5 && $bearing < 157.5) $direction = "northwest";
                    else if ($bearing >= 157.5) $direction = "north";
                    else if ($bearing < -22.5 && $bearing > -67.5) $direction = "southeast";
                    else if ($bearing <= -67.5 && $bearing > -112.5) $direction = "east";
                    else if ($bearing <= -112.5 && $bearing > -157.5) $direction = "northeast";
                    else if ($bearing <= -157.5) $direction = "north";
                    $distance = floor(sqrt(pow($deltaX, 2) + pow($deltaZ, 2)));
                    $sender->sendMessage(TextFormat::YELLOW . $plotFaction . "'s zona este la " . $distance . " blocuri " . $direction);
                }
                if (!$found) {
                    $sender->sendMessage(TextFormat::RED . "Nu sunt multe facțiuni in apropiere");
                } else {
                    $points = ["south", "west", "north", "east"];
                    $sender->sendMessage(TextFormat::YELLOW . "Te confrunți " . $points[$sender->getDirection()]);
                }
            }
        return true;
    }

    public function alphanum($string) {
        if (function_exists('ctype_alnum')) {
            $return = ctype_alnum($string);
        } else {
            $return = preg_match('/^[a-z0-9]+$/i', $string) > 0;
        }
        return $return;
    }
}
