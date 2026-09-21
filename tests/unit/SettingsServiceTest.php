<?php

declare(strict_types=1);

namespace OCA\Nostalgist\Tests\Unit;

use OCA\Nostalgist\Service\SettingsService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
	private const USER = 'alice';

	private function service(string $stored = ''): SettingsService {
		$config = $this->createStub(IConfig::class);
		$config->method('getUserValue')->willReturn($stored);
		return new SettingsService($config);
	}

	/**
	 * @return array<string, mixed> the settings as they are stored
	 */
	private function save(array $settings): array {
		$config = $this->createMock(IConfig::class);
		$saved = '';
		$config->method('setUserValue')->willReturnCallback(
			function (string $user, string $app, string $key, string $value) use (&$saved): void {
				$saved = $value;
			},
		);
		$config->method('getUserValue')->willReturnCallback(static fn (): string => $saved);
		(new SettingsService($config))->setUserSettings(self::USER, $settings);
		return json_decode($saved, true) ?? [];
	}

	public function testDefaultsAreReturnedWithoutStoredSettings(): void {
		$settings = $this->service()->getUserSettings(self::USER);
		$this->assertFalse($settings['video_smooth']);
		$this->assertSame(3, $settings['fastforward_ratio']);
		$this->assertSame(0, $settings['audio_volume']);
		$this->assertSame(64, $settings['audio_latency']);
		$this->assertTrue($settings['pause_when_hidden']);
		$this->assertTrue($settings['autosave_on_close']);
		$this->assertTrue($settings['respond_to_global_events']);
		$this->assertSame('/Games', $settings['library_folder']);
		$this->assertSame('', $settings['saves_folder']);
		$this->assertSame([], $settings['core_options']);
	}

	public function testBrokenStoredSettingsFallBackToTheDefaults(): void {
		$this->assertSame(
			$this->service()->getDefaults(),
			$this->service('not json at all')->getUserSettings(self::USER),
		);
	}

	public function testStoredSettingsOverrideTheDefaults(): void {
		$stored = json_encode(['video_smooth' => true, 'library_folder' => '/Roms']);
		$settings = $this->service($stored)->getUserSettings(self::USER);
		$this->assertTrue($settings['video_smooth']);
		$this->assertSame('/Roms', $settings['library_folder']);
		// Untouched settings keep their default.
		$this->assertSame(3, $settings['fastforward_ratio']);
	}

	public function testFoldersAreNormalized(): void {
		$saved = $this->save([
			'library_folder' => 'Games/Roms/',
			'saves_folder' => '  /Saves  ',
			'screenshots_folder' => '',
		]);
		$this->assertSame('/Games/Roms', $saved['library_folder']);
		$this->assertSame('/Saves', $saved['saves_folder']);
		$this->assertSame('', $saved['screenshots_folder'], 'an empty folder disables the feature');
	}

	public function testFoldersCannotEscapeTheUserFolder(): void {
		$saved = $this->save(['library_folder' => '../../etc', 'thumbnails_folder' => 'a/../../b']);
		$this->assertArrayNotHasKey('library_folder', $saved);
		$this->assertArrayNotHasKey('thumbnails_folder', $saved);
	}

	public function testTheLibraryFolderCannotBeEmptied(): void {
		$saved = $this->save(['library_folder' => '']);
		$this->assertArrayNotHasKey('library_folder', $saved);
	}

	public function testFastForwardRatioIsClamped(): void {
		// Stored as JSON, where a round number comes back as an integer.
		$this->assertEquals(5, $this->save(['fastforward_ratio' => 1000])['fastforward_ratio']);
		$this->assertEquals(1, $this->save(['fastforward_ratio' => -5])['fastforward_ratio']);
		$this->assertEquals(2.5, $this->save(['fastforward_ratio' => '2.5'])['fastforward_ratio']);
		$this->assertArrayNotHasKey('fastforward_ratio', $this->save(['fastforward_ratio' => 'fast']));
	}

	public function testAudioSettingsAreClamped(): void {
		$this->assertEquals(10, $this->save(['audio_volume' => 100])['audio_volume']);
		$this->assertEquals(-20, $this->save(['audio_volume' => -100])['audio_volume']);
		$this->assertEquals(-6, $this->save(['audio_volume' => '-6'])['audio_volume']);
		$this->assertArrayNotHasKey('audio_volume', $this->save(['audio_volume' => 'loud']));

		$this->assertSame(256, $this->save(['audio_latency' => 5000])['audio_latency']);
		$this->assertSame(16, $this->save(['audio_latency' => 0])['audio_latency']);
		$this->assertSame(96, $this->save(['audio_latency' => '96'])['audio_latency']);
	}

	public function testPlayerTogglesAreStoredAsBooleans(): void {
		$saved = $this->save([
			'scale_integer' => 'true',
			'pause_when_hidden' => '0',
			'autosave_on_close' => false,
		]);
		$this->assertTrue($saved['scale_integer']);
		$this->assertFalse($saved['pause_when_hidden']);
		$this->assertFalse($saved['autosave_on_close']);
	}

	public function testUnknownSettingsAreDropped(): void {
		$this->assertArrayNotHasKey('evil', $this->save(['evil' => 'value']));
	}

	public function testCoreOptionsAreCheckedAgainstWhatTheCoresOffer(): void {
		$saved = $this->save([
			'core_options' => [
				'fceumm' => [
					'fceumm_palette' => 'wavebeam',
					'fceumm_palette_made_up' => 'value',
					'fceumm_region' => 'Klingon',
				],
				'made_up_core' => ['option' => 'value'],
			],
		]);
		$this->assertSame(['fceumm' => ['fceumm_palette' => 'wavebeam']], $saved['core_options']);
	}
}
