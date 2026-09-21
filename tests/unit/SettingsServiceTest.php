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
		$this->assertSame(2, $settings['fastforward_ratio']);
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
		$this->assertSame(2, $settings['fastforward_ratio']);
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
		$this->assertEquals(50, $this->save(['fastforward_ratio' => 1000])['fastforward_ratio']);
		$this->assertEquals(0, $this->save(['fastforward_ratio' => -5])['fastforward_ratio']);
		$this->assertEquals(2.5, $this->save(['fastforward_ratio' => '2.5'])['fastforward_ratio']);
		$this->assertArrayNotHasKey('fastforward_ratio', $this->save(['fastforward_ratio' => 'fast']));
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
