<?php
ini_set('default_socket_timeout', 2);

require_once __DIR__ . '/../../data/config.php';
require_once __DIR__ . '/../../fonc/common.php';
require_once __DIR__ . '/../../fonc/monero.php';
require_once __DIR__ . '/../../fonc/wemo.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$poolData = get_nanopool($moneroPoolBalanceApi);
?>

<div class="row">
    <div class="col-md-6">
        <h4>Pending : <?= $poolData->data ?> <?= strtoupper($moneroCurrencyShort) ?></h4>
        <h4><a href="https://<?= $moneroCurrencyShort ?>.nanopool.org/account/<?= $moneroWallet ?>" target="_blank">Pool</a></h4>
        <h4><a href="https://coinmarketcap.com/currencies/<?= $moneroCurrencyLong ?>/" target="_blank">Market</a></h4>
    </div>
    <div class="col-md-6">
        <?php
        $coinmarketcap = get_coinmarketcap($moneroCurrencyLong);
        $unit_price = $coinmarketcap[0]->price_eur;
        $total_value = $moneroWalletAmount * $unit_price;
        ?>
        <h4>Currency price : <?= round($unit_price, 2) ?> €</h4>
        <h4>Wallet : <?= $moneroWalletAmount ?> <?= strtoupper($moneroCurrencyShort) ?></h4>
        <h4>Wallet value : <?= round($total_value, 2) ?> €</h4>
    </div>
</div>

<script src="template/<?= $template ?>/js/monero.js"></script>
<script src="template/<?= $template ?>/js/wemo.js"></script>

<p class="clearfix"></p>
<table class="table table-hover table-bordered">
    <tr><th>Worker</th><th>Hasrate</th><th>Uptime</th><th>Power</th><th>Power Cost</th><th>Status</th></tr>
    <?php
    $data = get_nanopool($moneroPoolWorkerApi);
    $last_machine = "";
    $last_uptime = "";
    $machines = $data->data;
    $power_refresh = "";
    $hash_refresh = "";
    $asToggle = false;

    foreach ($machines as $machine) {
        $name = $machine->id;
        $pieces = explode("-", $name);
        $hostname = $pieces[0];

        $link = 'http://' . $hostname . ':' . $moneroMachines[$name];

        $hashrate = $machine->hashrate;

        $uptime = '-';
        $lastShare = $machine->lastShare;

        if ((time() - $lastShare) < 3600) {
            $connection = @fsockopen($hostname, '22');
            if (is_resource($connection)) {
                $uptime = get_uptime($name, $last_machine, $last_uptime, $sshUser, $sshKeyPub, $sshKeyPriv);
                $hash_refresh .= "getHash('$link', '$name-hash');";
                $alivedMachines[] = $link;
            } else {
                $uptime = '-';
            }
            fclose($connection);
            echo '<tr class="success">';
        } else {
            echo '<tr class="danger">';
        }

        echo '<td><a href="' . $link . '" target="_blank">' . $name . '</a></td>';
        echo '<td><span id="' . $name . '-hash">' . $hashrate . ' ' . $moneroSpeedUnit . ' (pool)</span></td>';
        echo '<td>' . $uptime . '</td>';

        $power = '-';

        $wemoId = "wemo-$name";
        $ping = ping($wemoId);
        if ($ping) {
            $asToggle = true;
            $checked = '';
            $status = get_wemo_status($wemoId);

            $power = get_wemo_power($wemoId);

            $kwh = round(24 * 365 * ($power / 1000));
            $yearCost = round($kwh * $powerCost);
            $monthCost = round($yearCost / 12);

            $power_refresh .= "getPower('wemo-$name', '$name-power');";

            echo '<td><span id="' . $name . '-power">' . $power . '</span> W<br />' . $kwh . ' kWh / year</td>';
            echo '<td>' . $monthCost . ' € / month<br />' . $yearCost . ' € / year</td>';

            if ($status == 1) {
                $checked = 'checked ';
            }
            echo '<td><input ' . $checked . 'id="wemo-' . $name . '" class="btn-toggle wemo-insight" data-toggle="toggle" type="checkbox" autocomplete="off"></td>';
        } else {
            echo '<td>' . $power . '</td>';
            echo '<td>-</td>';
            echo '<td>-</td>';
        }

        echo '</tr>';

        $last_machine = $hostname;
        $last_uptime = $uptime;
    }
    ?>
    <tr>
        <th>Total</th>
        <th colspan="5">
            <?php
            $nanopoolUrl = "https://api.nanopool.org/v1/$moneroCurrencyShort/hashrate/$moneroWallet";
            $data = get_nanopool($nanopoolUrl);
            echo '<span id="total-hash">' . $data->data . ' ' . $moneroSpeedUnit . ' (pool)</span>';
            ?>
        </th>
    </tr>
</table>
<?php
if ($asToggle) {
    ?>
    <script>
        $(function () {
            $('.wemo-insight').bootstrapToggle();
            $('.wemo-insight').change(function () {
                checkedStatus = $(this).prop('checked');
                id = $(this).prop('id');

                $.ajax({
                    cache: false,
                    url: 'api/wemo.php',
                    type: 'GET',
                    data: 'id=' + id + '&action=' + checkedStatus,
                    dataType: 'html',

                    error: function (result, status, error) {
                        $('#alert').removeClass("hidden");
                        $('#alert').removeClass("alert-info");
                        $('#alert').addClass("alert-danger");
                        $('#alert').html('Error :  ' + error);
                    },
                });
            });
        })
    </script>
    <?php
}
?>
<?php
echo '<script>
        setInterval(function () {
            ' . ($power_refresh != '' ? $power_refresh : "") . '
            ' . ($hash_refresh != '' ? $hash_refresh : "") . '
            ' . (count($alivedMachines) != 0 ? 'getTotalHash(' . json_encode($alivedMachines) . ', "total-hash")' : "") . '
        }, 5000);
</script>';
?>